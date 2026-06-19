<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Client;
use App\Models\Product;
use App\Models\Provider;
use App\Models\Storage;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed Providers
        $ahmadTeaProvider = Provider::create(['name' => 'Ahmad Tea Provider']);
        $nestleProvider = Provider::create(['name' => 'Nestle Provider']);
        $unileverProvider = Provider::create(['name' => 'Unilever Provider']);

        // 2. Seed Categories
        // Ahmad Tea Categories
        $ahmadTeaRoot = Category::create([
            'name' => 'Ahmad Tea',
            'provider_id' => $ahmadTeaProvider->id,
        ]);
        $blackTea = Category::create([
            'name' => 'Black Tea',
            'parent_id' => $ahmadTeaRoot->id,
        ]);
        $greenTea = Category::create([
            'name' => 'Green Tea',
            'parent_id' => $ahmadTeaRoot->id,
        ]);

        // Nestle Categories
        $nestleRoot = Category::create([
            'name' => 'Beverages & Dairy',
            'provider_id' => $nestleProvider->id,
        ]);
        $coffee = Category::create([
            'name' => 'Coffee',
            'parent_id' => $nestleRoot->id,
        ]);
        $water = Category::create([
            'name' => 'Mineral Water',
            'parent_id' => $nestleRoot->id,
        ]);

        // Unilever Categories
        $unileverRoot = Category::create([
            'name' => 'Home Care',
            'provider_id' => $unileverProvider->id,
        ]);
        $laundry = Category::create([
            'name' => 'Laundry & Detergents',
            'parent_id' => $unileverRoot->id,
        ]);

        // 3. Seed Products
        Product::create([
            'name' => 'Ahmad Tea Earl Grey, 500g',
            'category_id' => $blackTea->id,
            'price' => 15.00, // Selling price
        ]);
        Product::create([
            'name' => 'Ahmad Tea Jasmine Green, 250g',
            'category_id' => $greenTea->id,
            'price' => 12.00,
        ]);
        Product::create([
            'name' => 'Nescafe Classic Coffee, 200g',
            'category_id' => $coffee->id,
            'price' => 18.00,
        ]);
        Product::create([
            'name' => 'Pure Life Mineral Water, 1.5L',
            'category_id' => $water->id,
            'price' => 3.50,
        ]);
        Product::create([
            'name' => 'Omo Detergent Powder, 1kg',
            'category_id' => $laundry->id,
            'price' => 10.00,
        ]);

        // 4. Seed Storages
        Storage::create(['name' => 'Main Warehouse']);
        Storage::create(['name' => 'Secondary Warehouse']);
        Storage::create(['name' => 'Cold Storage']);

        // 5. Seed Clients
        Client::create(['name' => 'Supermarket A']);
        Client::create(['name' => 'Mini Market B']);
        Client::create(['name' => 'Distributor C']);
    }
}
