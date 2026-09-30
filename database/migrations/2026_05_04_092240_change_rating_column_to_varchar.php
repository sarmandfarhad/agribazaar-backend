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
    public function up()
    {
        // The statements below are PostgreSQL-only; other drivers (the SQLite test database)
        // just need the same end result: a nullable small integer column.
        if (DB::getDriverName() !== 'pgsql') {
            Schema::table('farmer_products', function (Blueprint $table) {
                $table->smallInteger('rating')->nullable()->change();
            });

            return;
        }

        // Drop the existing check constraint if it exists
        DB::statement('ALTER TABLE farmer_products DROP CONSTRAINT IF EXISTS farmer_products_rating_check');

        // Update non-numeric values to NULL or a default value (3 stars)
        DB::statement("UPDATE farmer_products SET rating = '3' WHERE rating IS NULL OR rating !~ '^\d+$'");

        // Change the column type to smallInteger (for 1-5 range)
        DB::statement('ALTER TABLE farmer_products ALTER COLUMN rating TYPE smallint USING rating::smallint');

        // Add a check constraint to ensure values are between 1 and 5
        DB::statement('ALTER TABLE farmer_products ADD CONSTRAINT farmer_products_rating_check CHECK (rating >= 1 AND rating <= 5)');
    }

    public function down()
    {
        // Drop the check constraint
        DB::statement('ALTER TABLE farmer_products DROP CONSTRAINT IF EXISTS farmer_products_rating_check');

        // Revert to varchar
        DB::statement('ALTER TABLE farmer_products ALTER COLUMN rating TYPE varchar(255) USING rating::text');
    }
};
