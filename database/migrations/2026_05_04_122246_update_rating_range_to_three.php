<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // The constraint statements below are PostgreSQL-only; other drivers (the SQLite
        // test database) start empty and only need the new default.
        if (DB::getDriverName() !== 'pgsql') {
            Schema::table('order_items', function (Blueprint $table) {
                $table->integer('quality')->default(2)->change();
            });

            return;
        }

        // Update order_items quality
        DB::statement('ALTER TABLE order_items DROP CONSTRAINT IF EXISTS order_items_quality_check');
        DB::statement('UPDATE order_items SET quality = 3 WHERE quality >= 4');
        DB::statement('UPDATE order_items SET quality = 2 WHERE quality = 3');
        DB::statement('UPDATE order_items SET quality = 1 WHERE quality <= 2');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_quality_check CHECK (quality >= 1 AND quality <= 3)');
        DB::statement('ALTER TABLE order_items ALTER COLUMN quality SET DEFAULT 2');

        // Update farmer_products rating
        DB::statement('ALTER TABLE farmer_products DROP CONSTRAINT IF EXISTS farmer_products_rating_check');
        DB::statement('UPDATE farmer_products SET rating = 3 WHERE rating >= 4');
        DB::statement('UPDATE farmer_products SET rating = 2 WHERE rating = 3');
        DB::statement('UPDATE farmer_products SET rating = 1 WHERE rating <= 2');
        DB::statement('ALTER TABLE farmer_products ADD CONSTRAINT farmer_products_rating_check CHECK (rating >= 1 AND rating <= 3)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to 1-5 range if needed (not strictly required but good practice)
        DB::statement('ALTER TABLE order_items DROP CONSTRAINT IF EXISTS order_items_quality_check');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_quality_check CHECK (quality >= 1 AND quality <= 5)');

        DB::statement('ALTER TABLE farmer_products DROP CONSTRAINT IF EXISTS farmer_products_rating_check');
        DB::statement('ALTER TABLE farmer_products ADD CONSTRAINT farmer_products_rating_check CHECK (rating >= 1 AND rating <= 5)');
    }
};
