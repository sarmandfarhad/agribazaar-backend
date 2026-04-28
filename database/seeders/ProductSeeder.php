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
            'price_good'     => 2.50,
            'price_normal'   => 2.00,
            'price_bad'      => 1.50,
            'quantity'       => 100,
            'information'    => 'Fresh red organic tomatoes.',
            'category_id'    => $vegetableCategory?->id,
            'status'         => 'active',
            'total_quantity' => 100,
        ]);

        \App\Models\Product::create([
            'title'          => 'Green Onion',
            'price_good'     => 1.50,
            'price_normal'   => 1.25,
            'price_bad'      => 1.00,
            'quantity'       => 50,
            'information'    => 'Locally grown green onions.',
            'category_id'    => $vegetableCategory?->id,
            'status'         => 'active',
            'total_quantity' => 50,
        ]);

        \App\Models\Product::create([
            'title'          => 'Red Apple',
            'price_good'     => 3.50,
            'price_normal'   => 3.00,
            'price_bad'      => 2.50,
            'quantity'       => 80,
            'information'    => 'Crispy and sweet red apples.',
            'category_id'    => $fruitCategory?->id,
            'status'         => 'active',
            'total_quantity' => 80,
        ]);

        \App\Models\Product::create([
            'title'          => 'Basmati Rice',
            'price_good'     => 6.00,
            'price_normal'   => 5.50,
            'price_bad'      => 5.00,
            'quantity'       => 200,
            'information'    => 'Premium quality Basmati rice.',
            'category_id'    => $grainsCategory?->id,
            'status'         => 'active',
            'total_quantity' => 200,
        ]);
    }
}
