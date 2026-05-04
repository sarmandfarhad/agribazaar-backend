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

        // Additional admin products
        \App\Models\Product::create([
            'title'          => 'Carrot',
            'price_good'     => 1.80,
            'price_normal'   => 1.50,
            'price_bad'      => 1.20,
            'quantity'       => 120,
            'information'    => 'Fresh orange carrots, rich in vitamin A.',
            'category_id'    => $vegetableCategory?->id,
            'status'         => 'active',
            'total_quantity' => 120,
        ]);

        \App\Models\Product::create([
            'title'          => 'Banana',
            'price_good'     => 2.00,
            'price_normal'   => 1.75,
            'price_bad'      => 1.50,
            'quantity'       => 150,
            'information'    => 'Sweet and ripe bananas, perfect for smoothies.',
            'category_id'    => $fruitCategory?->id,
            'status'         => 'active',
            'total_quantity' => 150,
        ]);

        \App\Models\Product::create([
            'title'          => 'Spinach',
            'price_good'     => 1.25,
            'price_normal'   => 1.00,
            'price_bad'      => 0.75,
            'quantity'       => 60,
            'information'    => 'Organic leafy spinach, fresh from the farm.',
            'category_id'    => $vegetableCategory?->id,
            'status'         => 'active',
            'total_quantity' => 60,
        ]);

        \App\Models\Product::create([
            'title'          => 'Wheat Flour',
            'price_good'     => 4.50,
            'price_normal'   => 4.00,
            'price_bad'      => 3.50,
            'quantity'       => 250,
            'information'    => 'Whole grain wheat flour for baking.',
            'category_id'    => $grainsCategory?->id,
            'status'         => 'active',
            'total_quantity' => 250,
        ]);

        \App\Models\Product::create([
            'title'          => 'Orange',
            'price_good'     => 2.75,
            'price_normal'   => 2.50,
            'price_bad'      => 2.00,
            'quantity'       => 90,
            'information'    => 'Juicy and fresh oranges, packed with vitamin C.',
            'category_id'    => $fruitCategory?->id,
            'status'         => 'active',
            'total_quantity' => 90,
        ]);

        \App\Models\Product::create([
            'title'          => 'Cucumber',
            'price_good'     => 1.40,
            'price_normal'   => 1.20,
            'price_bad'      => 1.00,
            'quantity'       => 110,
            'information'    => 'Crisp and cool cucumbers, perfect for salads.',
            'category_id'    => $vegetableCategory?->id,
            'status'         => 'active',
            'total_quantity' => 110,
        ]);

        \App\Models\Product::create([
            'title'          => 'Corn',
            'price_good'     => 3.00,
            'price_normal'   => 2.50,
            'price_bad'      => 2.00,
            'quantity'       => 140,
            'information'    => 'Sweet yellow corn, great for grilling or boiling.',
            'category_id'    => $grainsCategory?->id,
            'status'         => 'active',
            'total_quantity' => 140,
        ]);
    }
}
