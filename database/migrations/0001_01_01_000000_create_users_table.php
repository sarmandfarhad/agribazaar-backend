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
        Schema::create('users', function (Blueprint $table) {
         $table->id();
        $table->string('phone')->unique()->nullable();
        $table->string('email')->unique()->nullable();
        $table->string('password');
        $table->enum('user_type', ['farmer', 'buyer', 'admin']);
        $table->enum('status', ['pending', 'approved', 'rejected', 'blocked'])->default('pending');
        $table->string('token')->nullable();
        $table->timestamps();
        });




    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');

    }
};
