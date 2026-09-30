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
        // The statements below are PostgreSQL-only; other drivers (the SQLite test database)
        // just need the same end result: an integer column.
        if (DB::getDriverName() !== 'pgsql') {
            Schema::table('order_items', function (Blueprint $table) {
                $table->integer('quality')->default(3)->change();
            });

            return;
        }

        // Drop existing constraint if it exists
        DB::statement('ALTER TABLE order_items DROP CONSTRAINT IF EXISTS order_items_quality_check');

        // Drop the default value
        DB::statement('ALTER TABLE order_items ALTER COLUMN quality DROP DEFAULT');

        // Set existing values to a numeric default (3) since the user said "don't map it"
        // This avoids casting errors from string to int
        DB::statement("UPDATE order_items SET quality = '3'");

        // Change the column type to integer
        DB::statement('ALTER TABLE order_items ALTER COLUMN quality TYPE integer USING quality::integer');

        // Set a new default value (integer)
        DB::statement('ALTER TABLE order_items ALTER COLUMN quality SET DEFAULT 3');

        // Add check constraint to ensure values are between 1 and 5
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_quality_check CHECK (quality >= 1 AND quality <= 5)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE order_items DROP CONSTRAINT IF EXISTS order_items_quality_check');
        DB::statement('ALTER TABLE order_items ALTER COLUMN quality DROP DEFAULT');
        DB::statement('ALTER TABLE order_items ALTER COLUMN quality TYPE varchar(255) USING quality::text');
        DB::statement("ALTER TABLE order_items ALTER COLUMN quality SET DEFAULT 'normal'");
    }
};
