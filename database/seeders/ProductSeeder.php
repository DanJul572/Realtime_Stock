<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * One sample product with a code, so it can be scanned and its label
     * printed right away. Safe to run again: the product is matched by code.
     */
    public function run(): void
    {
        $category = Category::firstOrCreate([
            'name' => 'Keramik'
        ]);

        Product::firstOrCreate(
            ['code' => 'RS-KRMK6X6A'],
            [
                'category_id' => $category->id,
                'name' => 'Keramik Lantai Putih',
                'price_1' => 150000,
                'price_2' => 135000,
                'size' => '60x60',
                'stock' => 100,
                'surface' => 'Glossy',
                'type' => 'Granit',
            ]
        );
    }
}
