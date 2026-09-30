<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_method')->default('delivery'); // delivery, pickup
            $table->unsignedInteger('delivery_fee')->default(0);     // whole IQD
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('distance_km', 8, 2)->nullable();
            // 0 for orders placed before the cancel window existed: the app treats them as not cancellable
            $table->unsignedInteger('cancel_window_seconds')->default(0);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn([
                'delivery_method',
                'delivery_fee',
                'latitude',
                'longitude',
                'distance_km',
                'cancel_window_seconds',
                'confirmed_at',
                'cancelled_at',
            ]);
        });
    }
};
