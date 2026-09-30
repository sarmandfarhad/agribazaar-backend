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
        // Single-row table: the one warehouse orders are delivered from / picked up at.
        Schema::create('delivery_settings', function (Blueprint $table) {
            $table->id();
            $table->string('warehouse_name');
            $table->string('warehouse_address');
            $table->decimal('warehouse_latitude', 10, 7);
            $table->decimal('warehouse_longitude', 10, 7);
            $table->timestamps();
        });

        Schema::create('delivery_tiers', function (Blueprint $table) {
            $table->id();
            $table->decimal('min_km', 8, 2);
            $table->decimal('max_km', 8, 2);
            $table->unsignedInteger('fee'); // whole IQD
            $table->timestamps();
        });

        // Seed the defaults here so production gets them by running migrations alone.
        $now = now();

        DB::table('delivery_settings')->insert([
            'warehouse_name'      => 'AgriBazaar Warehouse',
            'warehouse_address'   => '100m Street, Erbil',
            'warehouse_latitude'  => 36.1911,
            'warehouse_longitude' => 44.0092,
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);

        DB::table('delivery_tiers')->insert([
            ['min_km' => 0,  'max_km' => 10,  'fee' => 2000, 'created_at' => $now, 'updated_at' => $now],
            ['min_km' => 10, 'max_km' => 50,  'fee' => 3000, 'created_at' => $now, 'updated_at' => $now],
            ['min_km' => 50, 'max_km' => 200, 'fee' => 5000, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_tiers');
        Schema::dropIfExists('delivery_settings');
    }
};
