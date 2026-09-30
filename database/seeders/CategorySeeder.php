<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Category::updateOrCreate(['name' => 'Vegetable'], ['isActive' => true]);
        Category::updateOrCreate(['name' => 'Fruit'], ['isActive' => true]);
        Category::updateOrCreate(['name' => 'Grains'], ['isActive' => true]);
    }
}
