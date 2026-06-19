<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\BatchProduct;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientRefund;
use App\Models\ClientRefundAllocation;
use App\Models\ClientRefundItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAllocation;
use App\Models\Product;
use App\Models\Provider;
use App\Models\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventorySystemTest extends TestCase
{
    use RefreshDatabase;

    protected Provider $provider;

    protected Category $rootCategory;

    protected Category $childCategory;

    protected Product $product;

    protected Storage $storage;

    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed basic entities
        $this->provider = Provider::create(['name' => 'Ahmad Tea Provider']);

        $this->rootCategory = Category::create([
            'name' => 'Ahmad Tea',
            'provider_id' => $this->provider->id,
        ]);

        $this->childCategory = Category::create([
            'name' => 'Black Tea',
            'parent_id' => $this->rootCategory->id,
        ]);

        $this->product = Product::create([
            'name' => 'Ahmad Tea Earl Grey, 500g',
            'category_id' => $this->childCategory->id,
            'price' => 15.00, // Selling price
        ]);

        $this->storage = Storage::create(['name' => 'Main Warehouse']);
        $this->client = Client::create(['name' => 'Supermarket A']);
    }

    /**
     * Test POST /api/purchases
     */
    public function test_purchase_products_and_adds_to_storage(): void
    {
        $response = $this->postJson('/api/purchases', [
            'provider_id' => $this->provider->id,
            'storage_id' => $this->storage->id,
            'purchase_date' => '2026-06-19 10:00:00',
            'products' => [
                [
                    'product_id' => $this->product->id,
                    'qty' => 100,
                    'purchase_price' => 10.00,
                ],
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('batches', [
            'provider_id' => $this->provider->id,
            'storage_id' => $this->storage->id,
        ]);

        $this->assertDatabaseHas('batch_products', [
            'product_id' => $this->product->id,
            'quantity' => 100,
            'remaining_quantity' => 100,
            'purchase_price' => 10.00,
        ]);
    }

    /**
     * Test GET /api/products/available
     */
    public function test_fetch_available_products_for_ordering(): void
    {
        // Add two batches
        $batch1 = Batch::create([
            'provider_id' => $this->provider->id,
            'storage_id' => $this->storage->id,
            'purchase_date' => now(),
        ]);
        BatchProduct::create([
            'batch_id' => $batch1->id,
            'product_id' => $this->product->id,
            'quantity' => 30,
            'purchase_price' => 9.00,
            'remaining_quantity' => 30,
        ]);

        $batch2 = Batch::create([
            'provider_id' => $this->provider->id,
            'storage_id' => $this->storage->id,
            'purchase_date' => now(),
        ]);
        BatchProduct::create([
            'batch_id' => $batch2->id,
            'product_id' => $this->product->id,
            'quantity' => 20,
            'purchase_price' => 10.00,
            'remaining_quantity' => 20,
        ]);

        $response = $this->getJson('/api/products/available');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $this->product->id,
            'name' => 'Ahmad Tea Earl Grey, 500g',
            'category_name' => 'Black Tea',
            'price' => 15.00,
            'qty' => 50,
        ]);
    }

    /**
     * Test POST /api/orders (FIFO stocks allocation)
     */
    public function test_create_client_order_allocates_using_fifo(): void
    {
        // Batch 1: Oldest batch (purchase_date: 2026-06-10)
        $batchOldest = Batch::create([
            'provider_id' => $this->provider->id,
            'storage_id' => $this->storage->id,
            'purchase_date' => '2026-06-10 00:00:00',
        ]);
        $bp1 = BatchProduct::create([
            'batch_id' => $batchOldest->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'purchase_price' => 8.00,
            'remaining_quantity' => 10,
        ]);

        // Batch 2: Newer batch (purchase_date: 2026-06-15)
        $batchNewer = Batch::create([
            'provider_id' => $this->provider->id,
            'storage_id' => $this->storage->id,
            'purchase_date' => '2026-06-15 00:00:00',
        ]);
        $bp2 = BatchProduct::create([
            'batch_id' => $batchNewer->id,
            'product_id' => $this->product->id,
            'quantity' => 20,
            'purchase_price' => 9.50,
            'remaining_quantity' => 20,
        ]);

        // Order 15 items: expects 10 from oldest, 5 from newer
        $response = $this->postJson('/api/orders', [
            'client_id' => $this->client->id,
            'storage_id' => $this->storage->id,
            'order_date' => '2026-06-18 12:00:00',
            'products' => [
                [
                    'id' => $this->product->id,
                    'qty' => 15,
                ],
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);

        // Assert Batch 1 is fully depleted
        $bp1->refresh();
        $this->assertEquals(0, $bp1->remaining_quantity);

        // Assert Batch 2 has 15 units remaining
        $bp2->refresh();
        $this->assertEquals(15, $bp2->remaining_quantity);

        // Verify order items and allocations are saved
        $this->assertDatabaseHas('order_items', [
            'product_id' => $this->product->id,
            'quantity' => 15,
            'price' => 15.00,
        ]);

        $this->assertDatabaseHas('order_item_allocations', [
            'batch_product_id' => $bp1->id,
            'quantity' => 10,
        ]);

        $this->assertDatabaseHas('order_item_allocations', [
            'batch_product_id' => $bp2->id,
            'quantity' => 5,
        ]);
    }

    /**
     * Test POST /api/orders fails if stock is insufficient
     */
    public function test_create_order_fails_on_insufficient_stock(): void
    {
        $response = $this->postJson('/api/orders', [
            'client_id' => $this->client->id,
            'storage_id' => $this->storage->id,
            'products' => [
                [
                    'id' => $this->product->id,
                    'qty' => 50,
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }

    /**
     * Test POST /api/refunds/provider (Batch refund back to provider)
     */
    public function test_refund_purchased_products_on_batches(): void
    {
        $batch = Batch::create([
            'provider_id' => $this->provider->id,
            'storage_id' => $this->storage->id,
            'purchase_date' => now(),
        ]);
        $bp = BatchProduct::create([
            'batch_id' => $batch->id,
            'product_id' => $this->product->id,
            'quantity' => 50,
            'purchase_price' => 10.00,
            'remaining_quantity' => 50,
        ]);

        $response = $this->postJson('/api/refunds/provider', [
            'batch_id' => $batch->id,
            'products' => [
                [
                    'product_id' => $this->product->id,
                    'qty' => 20,
                ],
            ],
        ]);

        $response->assertStatus(200);
        $bp->refresh();
        $this->assertEquals(30, $bp->remaining_quantity);

        $this->assertDatabaseHas('provider_refunds', ['batch_id' => $batch->id]);
        $this->assertDatabaseHas('provider_refund_items', [
            'product_id' => $this->product->id,
            'quantity' => 20,
        ]);
    }

    /**
     * Test client refund restores batch stocks
     */
    public function test_refund_from_client_restores_stocks(): void
    {
        // Purchase a batch
        $batch = Batch::create([
            'provider_id' => $this->provider->id,
            'storage_id' => $this->storage->id,
            'purchase_date' => '2026-06-01 10:00:00',
        ]);
        $bp = BatchProduct::create([
            'batch_id' => $batch->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'purchase_price' => 10.00,
            'remaining_quantity' => 10,
        ]);

        // Place an order
        $order = Order::create([
            'client_id' => $this->client->id,
            'storage_id' => $this->storage->id,
            'order_date' => '2026-06-05 10:00:00',
        ]);
        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 4,
            'price' => 15.00,
        ]);
        $alloc = OrderItemAllocation::create([
            'order_item_id' => $orderItem->id,
            'batch_product_id' => $bp->id,
            'quantity' => 4,
        ]);

        $bp->decrement('remaining_quantity', 4); // Remaining: 6

        // Refund 2 items
        $response = $this->postJson('/api/refunds/client', [
            'order_id' => $order->id,
            'products' => [
                [
                    'id' => $this->product->id,
                    'qty' => 2,
                ],
            ],
        ]);

        $response->assertStatus(200);

        // Remaining should go back up to 8 (6 + 2)
        $bp->refresh();
        $this->assertEquals(8, $bp->remaining_quantity);

        $this->assertDatabaseHas('client_refunds', ['order_id' => $order->id]);
        $this->assertDatabaseHas('client_refund_items', [
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);
        $this->assertDatabaseHas('client_refund_allocations', [
            'order_item_allocation_id' => $alloc->id,
            'quantity' => 2,
        ]);
    }

    /**
     * Test GET /api/storages/remaining-quantities
     */
    public function test_calculate_remaining_quantities_by_date(): void
    {
        // 1. Purchase on June 5
        $batch = Batch::create([
            'provider_id' => $this->provider->id,
            'storage_id' => $this->storage->id,
            'purchase_date' => '2026-06-05 09:00:00',
        ]);
        $bp = BatchProduct::create([
            'batch_id' => $batch->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'purchase_price' => 10.00,
            'remaining_quantity' => 10,
        ]);

        // 2. Order on June 10
        $order = Order::create([
            'client_id' => $this->client->id,
            'storage_id' => $this->storage->id,
            'order_date' => '2026-06-10 10:00:00',
        ]);
        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 4,
            'price' => 15.00,
        ]);
        OrderItemAllocation::create([
            'order_item_id' => $orderItem->id,
            'batch_product_id' => $bp->id,
            'quantity' => 4,
        ]);
        $bp->decrement('remaining_quantity', 4);

        // Check stock on June 6 (should be 10, because sale on June 10 has not happened yet)
        $responseJune6 = $this->getJson('/api/storages/remaining-quantities?date=2026-06-06');
        $responseJune6->assertStatus(200);
        $responseJune6->assertJsonFragment([
            'product_id' => $this->product->id,
            'qty' => 10,
        ]);

        // Check stock on June 11 (should be 6, sale of 4 on June 10 is factored)
        $responseJune11 = $this->getJson('/api/storages/remaining-quantities?date=2026-06-11');
        $responseJune11->assertStatus(200);
        $responseJune11->assertJsonFragment([
            'product_id' => $this->product->id,
            'qty' => 6,
        ]);
    }

    /**
     * Test GET /api/batches/profit
     */
    public function test_calculate_profit_per_batch(): void
    {
        // Purchase a batch: Cost = $10.00 per unit, total 10 units. Total possible cost = $100.
        $batch = Batch::create([
            'provider_id' => $this->provider->id,
            'storage_id' => $this->storage->id,
            'purchase_date' => '2026-06-01 10:00:00',
        ]);
        $bp = BatchProduct::create([
            'batch_id' => $batch->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'purchase_price' => 10.00,
            'remaining_quantity' => 10,
        ]);

        // Order 5 units at selling price $15.00. Revenue = $75. Cost = $50. Profit = $25.
        $order = Order::create([
            'client_id' => $this->client->id,
            'storage_id' => $this->storage->id,
            'order_date' => '2026-06-05 10:00:00',
        ]);
        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'price' => 15.00,
        ]);
        $alloc = OrderItemAllocation::create([
            'order_item_id' => $orderItem->id,
            'batch_product_id' => $bp->id,
            'quantity' => 5,
        ]);
        $bp->decrement('remaining_quantity', 5);

        // Verify profit: expected profit = 5 * ($15.00 - $10.00) = $25.00
        $response1 = $this->getJson('/api/batches/profit');
        $response1->assertStatus(200);
        $response1->assertJsonFragment([
            'batch_id' => $batch->id,
            'profit' => 25.00,
        ]);

        // Refund 2 units. Net sold = 3 units. Revenue = $45. Cost = $30. Profit = $15.00.
        $clientRefund = ClientRefund::create([
            'order_id' => $order->id,
            'refund_date' => '2026-06-08 10:00:00',
        ]);
        $refundItem = ClientRefundItem::create([
            'client_refund_id' => $clientRefund->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);
        ClientRefundAllocation::create([
            'client_refund_item_id' => $refundItem->id,
            'order_item_allocation_id' => $alloc->id,
            'quantity' => 2,
        ]);
        $bp->increment('remaining_quantity', 2);

        // Verify updated profit: expected profit = 3 * ($15.00 - $10.00) = $15.00
        $response2 = $this->getJson('/api/batches/profit');
        $response2->assertStatus(200);
        $response2->assertJsonFragment([
            'batch_id' => $batch->id,
            'profit' => 15.00,
        ]);
    }

    /**
     * Test POST /api/orders fails if product selling price is <= provider purchase price
     */
    public function test_create_order_fails_when_selling_price_not_higher(): void
    {
        // Batch purchase price = $15.00, client selling price is $15.00 (not higher)
        $batch = Batch::create([
            'provider_id' => $this->provider->id,
            'storage_id' => $this->storage->id,
            'purchase_date' => now(),
        ]);
        BatchProduct::create([
            'batch_id' => $batch->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'purchase_price' => 15.00,
            'remaining_quantity' => 10,
        ]);

        $response = $this->postJson('/api/orders', [
            'client_id' => $this->client->id,
            'storage_id' => $this->storage->id,
            'products' => [
                [
                    'id' => $this->product->id,
                    'qty' => 5,
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }

    /**
     * Test client refund returns stock to active storage (not the original storage)
     */
    public function test_refund_client_returns_to_active_storage(): void
    {
        // 1. Purchase a batch in Main Warehouse ($this->storage)
        $batchMain = Batch::create([
            'provider_id' => $this->provider->id,
            'storage_id' => $this->storage->id,
            'purchase_date' => '2026-06-01 10:00:00',
        ]);
        $bpMain = BatchProduct::create([
            'batch_id' => $batchMain->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'purchase_price' => 10.00,
            'remaining_quantity' => 10,
        ]);

        // Place an order from Main Warehouse
        $order = Order::create([
            'client_id' => $this->client->id,
            'storage_id' => $this->storage->id,
            'order_date' => '2026-06-05 10:00:00',
        ]);
        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'price' => 15.00,
        ]);
        $alloc = OrderItemAllocation::create([
            'order_item_id' => $orderItem->id,
            'batch_product_id' => $bpMain->id,
            'quantity' => 5,
        ]);
        $bpMain->decrement('remaining_quantity', 5); // Remaining in Main: 5

        // Create secondary storage
        $secondaryStorage = \App\Models\Storage::create(['name' => 'Secondary Warehouse']);

        // Refund 2 items to active storage = secondary storage
        $response = $this->postJson('/api/refunds/client', [
            'order_id' => $order->id,
            'storage_id' => $secondaryStorage->id,
            'products' => [
                [
                    'id' => $this->product->id,
                    'qty' => 2,
                ],
            ],
        ]);

        $response->assertStatus(200);

        // Original Main warehouse batch remaining_quantity should still be 5
        $bpMain->refresh();
        $this->assertEquals(5, $bpMain->remaining_quantity);

        // A batch product should be created in the secondary warehouse storage and have remaining_quantity = 2
        $bpSecondary = BatchProduct::join('batches', 'batch_products.batch_id', '=', 'batches.id')
            ->where('batches.storage_id', $secondaryStorage->id)
            ->where('batch_products.product_id', $this->product->id)
            ->select('batch_products.*')
            ->first();

        $this->assertNotNull($bpSecondary);
        $this->assertEquals(2, $bpSecondary->remaining_quantity);
    }

    /**
     * Test POST /api/purchases fails if purchase price is not lower than product selling price
     */
    public function test_purchase_fails_when_purchase_price_not_lower(): void
    {
        // Product price is $15.00. We try to purchase at $15.00 (not lower).
        $response = $this->postJson('/api/purchases', [
            'provider_id' => $this->provider->id,
            'storage_id' => $this->storage->id,
            'purchase_date' => '2026-06-19 10:00:00',
            'products' => [
                [
                    'product_id' => $this->product->id,
                    'qty' => 100,
                    'purchase_price' => 15.00,
                ],
            ],
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('success', false);
    }
}
