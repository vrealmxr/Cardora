<?php

namespace Database\Seeders;

use App\Models\PageSeoSetting;
use Illuminate\Database\Seeder;

class PageSeoSettingsSeeder extends Seeder
{
    protected const PAGE_KEYS = [
        'home',
        'category.cards',
        'category.figures',
        'category.comics',
        'category.misc',
        'about',
        'draws',
        'blog',
        'contact',
        'faq',
        'support-center',
        'cardora-pro',
        'terms',
        'privacy',
        'cookies',
        'refunds-disputes',
    ];

    /**
     * Pre-seed one row per page_key x locale, left null so the frontend falls back to its
     * existing hardcoded copy until the SEO editor actually fills something in.
     */
    public function run(): void
    {
        foreach (self::PAGE_KEYS as $pageKey) {
            foreach (['el', 'en'] as $locale) {
                PageSeoSetting::query()->firstOrCreate([
                    'page_key' => $pageKey,
                    'locale' => $locale,
                ]);
            }
        }
    }
}
