<?php

namespace Database\Seeders;

use App\Models\Buyer;
use App\Models\Farmer;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $farmerUser = User::updateOrCreate(
            ['phone' => '07518456346'],
            [
                'email' => null,
                'password' => 'test1234',
                'user_type' => 'farmer',
                'status' => 'approved',
            ]
        );

        Farmer::updateOrCreate(
            ['user_id' => $farmerUser->id],
            [
                'first_name' => 'Test',
                'second_name' => 'Farmer',
                'address' => 'Baghdad',
                'city' => 'Baghdad',
            ]
        );

        $buyerUser = User::updateOrCreate(
            ['phone' => '07508456346'],
            [
                'email' => null,
                'password' => 'test1234',
                'user_type' => 'buyer',
                'status' => 'approved',
            ]
        );

        Buyer::updateOrCreate(
            ['user_id' => $buyerUser->id],
            [
                'first_name' => 'Test',
                'second_name' => 'Buyer',
                'address' => 'Baghdad',
                'city' => 'Baghdad',
                'business_name' => 'Test Buyer Shop',
            ]
        );
    }
}
