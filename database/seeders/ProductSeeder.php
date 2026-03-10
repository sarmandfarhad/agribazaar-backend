<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vegetableCategory = \App\Models\Category::where('name', 'Vegetable')->first();
        $fruitCategory     = \App\Models\Category::where('name', 'Fruit')->first();
        $grainsCategory    = \App\Models\Category::where('name', 'Grains')->first();

        \App\Models\Product::create([
            'title'          => 'Red Tomato',
            'price_per_kilo' => 2.50,
            'quantity'       => 100,
            'information'    => 'Fresh red organic tomatoes.',
            'category_id'    => $vegetableCategory?->id,
            'status'         => 'active',
            'total_quantity' => 100,
        ]);

        \App\Models\Product::create([
            'title'          => 'Green Onion',
            'price_per_kilo' => 1.25,
            'quantity'       => 50,
            'information'    => 'Locally grown green onions.',
            'category_id'    => $vegetableCategory?->id,
            'status'         => 'active',
            'total_quantity' => 50,
        ]);

        \App\Models\Product::create([
            'title'          => 'Red Apple',
            'price_per_kilo' => 3.00,
            'quantity'       => 80,
            'information'    => 'Crispy and sweet red apples.',
            'category_id'    => $fruitCategory?->id,
            'status'         => 'active',
            'total_quantity' => 80,
        ]);

        \App\Models\Product::create([
            'title'          => 'Basmati Rice',
            'price_per_kilo' => 5.50,
            'quantity'       => 200,
            'information'    => 'Premium quality Basmati rice.',
            'category_id'    => $grainsCategory?->id,
            'status'         => 'active',
            'total_quantity' => 200,
        ]);
    }
}
