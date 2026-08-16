<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $brands = [
            ['name' => 'Nike', 'description' => 'Just do it'],
            ['name' => 'Adidas', 'description' => 'Impossible is Nothing'],
            ['name' => 'Compass', 'description' => 'Compass untuk Semua'],
        ];

        foreach ($brands as $brand) {
            Brand::create($brand);
        }
    }
}
