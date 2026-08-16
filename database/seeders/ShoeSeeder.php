<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Shoe;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ShoeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sneakers = Category::where('name', 'Sneakers')->firstOrFail();
        $casual = Category::where('name', 'Casual')->firstOrFail();
        $boots = Category::where('name', 'Boots')->firstOrFail();

        $nike = Brand::where('name', 'Nike')->firstOrFail();
        $adidas = Brand::where('name', 'Adidas')->firstOrFail();
        $compass = Brand::where('name', 'Compass')->firstOrFail();

        $shoes = [
            [
                'category_id' => $sneakers->id,
                'brand_id' => $nike->id,
                'name' => 'Nike Air Max 90',
                'size' => '42',
                'price' => 2500000,
                'stock' => 10,
                'description' => 'The Nike Air Max 90 is a classic sneaker that has been a favorite among sneaker enthusiasts for decades. With its iconic design and comfortable fit, it is perfect for everyday wear.',
            ],
            [
                'category_id' => $casual->id,
                'brand_id' => $adidas->id,
                'name' => 'Adidas Samba Og',
                'size' => '42',
                'price' => 2000000,
                'stock' => 10,
                'description' => 'The Adidas Samba Og is a timeless classic that has been a favorite among sneaker enthusiasts for decades.',
            ],
        ];

        foreach ($shoes as $shoe) {
            Shoe::create($shoe);
        }
    }
}
