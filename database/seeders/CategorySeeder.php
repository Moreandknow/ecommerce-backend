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
        $categories = [
            [
                'name' => 'Elektronik',
                'icon' => 'category/Elektronik.png',
                'childs' => ['Microwave', 'TV'],
            ],
            [
                'name' => 'Fashion Pria',
                'icon' => 'category/Fashion-Pria.png',
                'childs' => ['Kemeja', 'Jas'],
            ],
            [
                'name' => 'Fashion Wanita',
                'icon' => 'category/Fashion-Wanita.png',
                'childs' => ['Dress', 'Jas'],
            ],
            [
                'name' => 'Handphone',
                'icon' => 'category/Handphone.png',
                'childs' => ['Apple', 'Samsung'],
            ],
            [
                'name' => 'Komputer & Laptop',
                'icon' => 'category/Komputer-Laptop.png',
                'childs' => ['Keyboard', 'Laptop'],
            ],
            [
                'name' => 'Makanan & Minuman',
                'icon' => 'category/Makanan-Minuman.png',
                'childs' => ['Pizza', 'Kopi'],
            ],
        ];

        foreach ($categories as $categoryPayload) {
        $category = \App\Models\Category::create([
            'slug' => \Str::slug($categoryPayload['name']),
            'name' => $categoryPayload['name'],
            'icon' => $categoryPayload['icon'],
        ]);

        // Move the second loop inside the first loop
        foreach ($categoryPayload['childs'] as $child) {
            $category->childs()->create([
                'slug' => \Str::slug($child),
                'name' => $child,
                ]);
            }
        }
    }
}
