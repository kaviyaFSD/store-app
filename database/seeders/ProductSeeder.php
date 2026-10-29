<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use \App\Models\Product;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
{
    $products = [
        ['name' => 'Colgate Toothpaste', 'code' => 'COL-001', 'price' => 50, 'tax_percent' => 8, 'stock' => 40],
        ['name' => 'Parle-G Biscuit',    'code' => 'PAR-001', 'price' => 10, 'tax_percent' => 8, 'stock' => 100],
        ['name' => 'Bread',              'code' => 'BRD-001', 'price' => 40, 'tax_percent' => 5, 'stock' => 4],
        ['name' => 'Milk 1L',            'code' => 'MLK-001', 'price' => 60, 'tax_percent' => 5, 'stock' => 9],
        ['name' => 'Eggs (12)',          'code' => 'EGG-001', 'price' => 80, 'tax_percent' => 5, 'stock' => 2],
    ];

    foreach ($products as $product) {
       Product::create($product);
    }
}
}
