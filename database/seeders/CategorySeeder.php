<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\Category::updateOrCreate(['name' => 'Vegetable'], ['isActive' => true]);
        \App\Models\Category::updateOrCreate(['name' => 'Fruit'], ['isActive' => true]);
        \App\Models\Category::updateOrCreate(['name' => 'Grains'], ['isActive' => true]);
    }
}
