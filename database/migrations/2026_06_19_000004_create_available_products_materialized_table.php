<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create Table
        Schema::create('available_products_materialized', function (Blueprint $table) {
            $table->foreignId('product_id')->primary()->constrained('products')->cascadeOnDelete();
            $table->string('name');
            $table->string('category_name');
            $table->decimal('price', 10, 2);
            $table->integer('qty');
            $table->timestamps();
        });

        // 2. Populate Initial Data
        $initialData = DB::table('products')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->leftJoin('batch_products', 'products.id', '=', 'batch_products.product_id')
            ->select(
                'products.id as product_id',
                'products.name',
                'categories.name as category_name',
                'products.price',
                DB::raw('COALESCE(SUM(batch_products.remaining_quantity), 0) as qty')
            )
            ->groupBy('products.id', 'products.name', 'categories.name', 'products.price')
            ->get();

        foreach ($initialData as $row) {
            DB::table('available_products_materialized')->insert([
                'product_id' => $row->product_id,
                'name' => $row->name,
                'category_name' => $row->category_name,
                'price' => $row->price,
                'qty' => $row->qty,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 3. Register Triggers
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            $this->createMysqlTriggers();
        } elseif ($driver === 'sqlite') {
            $this->createSqliteTriggers();
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            $this->dropMysqlTriggers();
        }

        Schema::dropIfExists('available_products_materialized');
    }

    protected function createMysqlTriggers(): void
    {
        DB::unprepared("
            CREATE TRIGGER trg_products_after_insert
            AFTER INSERT ON products
            FOR EACH ROW
            BEGIN
                INSERT INTO available_products_materialized (product_id, name, category_name, price, qty, created_at, updated_at)
                SELECT 
                    NEW.id, NEW.name, c.name, NEW.price, 0, NOW(), NOW()
                FROM categories c
                WHERE c.id = NEW.category_id;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER trg_products_after_update
            AFTER UPDATE ON products
            FOR EACH ROW
            BEGIN
                UPDATE available_products_materialized apm
                JOIN categories c ON c.id = NEW.category_id
                SET 
                    apm.name = NEW.name,
                    apm.category_name = c.name,
                    apm.price = NEW.price,
                    apm.updated_at = NOW()
                WHERE apm.product_id = NEW.id;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER trg_categories_after_update
            AFTER UPDATE ON categories
            FOR EACH ROW
            BEGIN
                UPDATE available_products_materialized apm
                JOIN products p ON apm.product_id = p.id
                SET 
                    apm.category_name = NEW.name,
                    apm.updated_at = NOW()
                WHERE p.category_id = NEW.id;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER trg_batch_products_after_insert
            AFTER INSERT ON batch_products
            FOR EACH ROW
            BEGIN
                INSERT INTO available_products_materialized (product_id, name, category_name, price, qty, created_at, updated_at)
                SELECT 
                    p.id, p.name, c.name, p.price, COALESCE(SUM(bp.remaining_quantity), 0), NOW(), NOW()
                FROM products p
                JOIN categories c ON p.category_id = c.id
                LEFT JOIN batch_products bp ON p.id = bp.product_id
                WHERE p.id = NEW.product_id
                GROUP BY p.id, p.name, c.name, p.price
                ON DUPLICATE KEY UPDATE 
                    qty = VALUES(qty),
                    updated_at = NOW();
            END
        ");

        DB::unprepared("
            CREATE TRIGGER trg_batch_products_after_update
            AFTER UPDATE ON batch_products
            FOR EACH ROW
            BEGIN
                INSERT INTO available_products_materialized (product_id, name, category_name, price, qty, created_at, updated_at)
                SELECT 
                    p.id, p.name, c.name, p.price, COALESCE(SUM(bp.remaining_quantity), 0), NOW(), NOW()
                FROM products p
                JOIN categories c ON p.category_id = c.id
                LEFT JOIN batch_products bp ON p.id = bp.product_id
                WHERE p.id = NEW.product_id
                GROUP BY p.id, p.name, c.name, p.price
                ON DUPLICATE KEY UPDATE 
                    qty = VALUES(qty),
                    updated_at = NOW();

                IF OLD.product_id <> NEW.product_id THEN
                    INSERT INTO available_products_materialized (product_id, name, category_name, price, qty, created_at, updated_at)
                    SELECT 
                        p.id, p.name, c.name, p.price, COALESCE(SUM(bp.remaining_quantity), 0), NOW(), NOW()
                    FROM products p
                    JOIN categories c ON p.category_id = c.id
                    LEFT JOIN batch_products bp ON p.id = bp.product_id
                    WHERE p.id = OLD.product_id
                    GROUP BY p.id, p.name, c.name, p.price
                    ON DUPLICATE KEY UPDATE 
                        qty = VALUES(qty),
                        updated_at = NOW();
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER trg_batch_products_after_delete
            AFTER DELETE ON batch_products
            FOR EACH ROW
            BEGIN
                INSERT INTO available_products_materialized (product_id, name, category_name, price, qty, created_at, updated_at)
                SELECT 
                    p.id, p.name, c.name, p.price, COALESCE(SUM(bp.remaining_quantity), 0), NOW(), NOW()
                FROM products p
                JOIN categories c ON p.category_id = c.id
                LEFT JOIN batch_products bp ON p.id = bp.product_id
                WHERE p.id = OLD.product_id
                GROUP BY p.id, p.name, c.name, p.price
                ON DUPLICATE KEY UPDATE 
                    qty = VALUES(qty),
                    updated_at = NOW();
            END
        ");
    }

    protected function createSqliteTriggers(): void
    {
        DB::unprepared("
            CREATE TRIGGER trg_products_after_insert
            AFTER INSERT ON products
            BEGIN
                INSERT INTO available_products_materialized (product_id, name, category_name, price, qty, created_at, updated_at)
                SELECT 
                    NEW.id, NEW.name, c.name, NEW.price, 0, datetime('now'), datetime('now')
                FROM categories c
                WHERE c.id = NEW.category_id;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER trg_products_after_update
            AFTER UPDATE ON products
            BEGIN
                UPDATE available_products_materialized
                SET 
                    name = NEW.name,
                    category_name = (SELECT name FROM categories WHERE id = NEW.category_id),
                    price = NEW.price,
                    updated_at = datetime('now')
                WHERE product_id = NEW.id;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER trg_categories_after_update
            AFTER UPDATE ON categories
            BEGIN
                UPDATE available_products_materialized
                SET 
                    category_name = NEW.name,
                    updated_at = datetime('now')
                WHERE product_id IN (SELECT id FROM products WHERE category_id = NEW.id);
            END
        ");

        DB::unprepared("
            CREATE TRIGGER trg_batch_products_after_insert
            AFTER INSERT ON batch_products
            BEGIN
                INSERT INTO available_products_materialized (product_id, name, category_name, price, qty, created_at, updated_at)
                SELECT 
                    p.id, p.name, c.name, p.price, COALESCE(SUM(bp.remaining_quantity), 0), datetime('now'), datetime('now')
                FROM products p
                JOIN categories c ON p.category_id = c.id
                LEFT JOIN batch_products bp ON p.id = bp.product_id
                WHERE p.id = NEW.product_id
                GROUP BY p.id, p.name, c.name, p.price
                ON CONFLICT(product_id) DO UPDATE SET
                    qty = excluded.qty,
                    updated_at = excluded.updated_at;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER trg_batch_products_after_update
            AFTER UPDATE ON batch_products
            BEGIN
                INSERT INTO available_products_materialized (product_id, name, category_name, price, qty, created_at, updated_at)
                SELECT 
                    p.id, p.name, c.name, p.price, COALESCE(SUM(bp.remaining_quantity), 0), datetime('now'), datetime('now')
                FROM products p
                JOIN categories c ON p.category_id = c.id
                LEFT JOIN batch_products bp ON p.id = bp.product_id
                WHERE p.id = NEW.product_id
                GROUP BY p.id, p.name, c.name, p.price
                ON CONFLICT(product_id) DO UPDATE SET
                    qty = excluded.qty,
                    updated_at = excluded.updated_at;

                INSERT INTO available_products_materialized (product_id, name, category_name, price, qty, created_at, updated_at)
                SELECT 
                    p.id, p.name, c.name, p.price, COALESCE(SUM(bp.remaining_quantity), 0), datetime('now'), datetime('now')
                FROM products p
                JOIN categories c ON p.category_id = c.id
                LEFT JOIN batch_products bp ON p.id = bp.product_id
                WHERE p.id = OLD.product_id
                GROUP BY p.id, p.name, c.name, p.price
                ON CONFLICT(product_id) DO UPDATE SET
                    qty = excluded.qty,
                    updated_at = excluded.updated_at;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER trg_batch_products_after_delete
            AFTER DELETE ON batch_products
            BEGIN
                INSERT INTO available_products_materialized (product_id, name, category_name, price, qty, created_at, updated_at)
                SELECT 
                    p.id, p.name, c.name, p.price, COALESCE(SUM(bp.remaining_quantity), 0), datetime('now'), datetime('now')
                FROM products p
                JOIN categories c ON p.category_id = c.id
                LEFT JOIN batch_products bp ON p.id = bp.product_id
                WHERE p.id = OLD.product_id
                GROUP BY p.id, p.name, c.name, p.price
                ON CONFLICT(product_id) DO UPDATE SET
                    qty = excluded.qty,
                    updated_at = excluded.updated_at;
            END
        ");
    }

    protected function dropMysqlTriggers(): void
    {
        DB::unprepared("DROP TRIGGER IF EXISTS trg_products_after_insert");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_products_after_update");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_categories_after_update");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_batch_products_after_insert");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_batch_products_after_update");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_batch_products_after_delete");
    }
};
