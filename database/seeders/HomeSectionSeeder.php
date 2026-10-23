<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\ProductSection;
use Illuminate\Database\Seeder;

/** Starting rows for the home page. Safe to run again: existing sections are left alone. */
class HomeSectionSeeder extends Seeder
{
    public function run(): void
    {
        $sections = [
            ['Flash deals', 'Big savings while stock lasts', 'discounted', null],
            ['Best sellers', 'What Tanzania is buying', 'best_selling', null],
            ['New arrivals', null, 'newest', null],
            ['Home & kitchen favourites', null, 'category', 'home-kitchen'],
        ];

        foreach ($sections as $order => [$title, $subtitle, $source, $categorySlug]) {
            ProductSection::firstOrCreate(['title' => $title], [
                'subtitle' => $subtitle,
                'source' => $source,
                'category_id' => $categorySlug ? Category::where('slug', $categorySlug)->value('id') : null,
                'product_limit' => 10,
                'sort_order' => $order,
                'is_active' => true,
            ]);
        }
    }
}
