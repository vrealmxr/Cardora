<?php

namespace App\Support;

use Illuminate\Support\Arr;

class CardoraCatalog
{
    public static function categories(): array
    {
        $subcategories = self::categorySubcategories();
        $spotlights = self::categorySpotlightFilters();

        return [
            'cards' => [
                'slug' => 'kartes',
                'icon' => 'sparkles',
                'sort_order' => 1,
                'translations' => [
                    'el' => [
                        'name' => 'Κάρτες',
                        'tagline' => 'TCG, graded slabs, sealed προϊόντα και premium μονές κάρτες',
                        'short_description' => 'Η βασική premium κατηγορία για trading cards.',
                        'description' => 'Η πιο δυνατή κατηγορία του Cardora, με Pokémon, Yu-Gi-Oh!, Magic, One Piece και sports cards για σοβαρούς συλλέκτες.',
                        'market_label' => 'Cardora Market',
                        'subcategories' => $subcategories['cards']['el'],
                        'product_types' => self::withAllOption($subcategories['cards']['el'], 'el'),
                        'filters' => [
                            'prices' => ['Όλες', 'Έως 50€', '50€ - 150€', '150€ - 500€', '500€ - 1.500€', '1.500€+'],
                            'conditions' => ['Όλες', 'Mint', 'Near Mint', 'Excellent', 'Very Good', 'Sealed'],
                            'rarities' => ['Όλες', 'Alt Art', 'Secret Rare', 'Ultra Rare', 'Legendary', 'Limited'],
                            'franchises' => ['Όλες', 'Pokémon', 'Yu-Gi-Oh!', 'Magic: The Gathering', 'One Piece', 'Sports Cards'],
                            'brands' => ['Όλες', 'PSA', 'BGS', 'CGC', 'Topps', 'Bandai', 'The Pokémon Company', 'Wizards'],
                            'graded' => ['Όλες', 'Graded', 'Ungraded'],
                            'availability' => ['Όλες', 'Άμεσα διαθέσιμο', '1-2 ημέρες', 'Προπαραγγελία'],
                            'seller_ratings' => ['Όλες', '4.0+', '4.5+', '4.8+'],
                        ],
                        'spotlight_filters' => $spotlights['cards']['el'],
                    ],
                    'en' => [
                        'name' => 'Cards',
                        'tagline' => 'TCG, graded slabs, sealed products and premium single cards',
                        'short_description' => 'The main premium category for trading cards.',
                        'description' => 'Cardora’s strongest category, with Pokémon, Yu-Gi-Oh!, Magic, One Piece and sports cards for serious collectors.',
                        'market_label' => 'Cardora Market',
                        'subcategories' => $subcategories['cards']['en'],
                        'product_types' => self::withAllOption($subcategories['cards']['en'], 'en'),
                        'filters' => [
                            'prices' => ['All', 'Up to €50', '€50 - €150', '€150 - €500', '€500 - €1,500', '€1,500+'],
                            'conditions' => ['All', 'Mint', 'Near Mint', 'Excellent', 'Very Good', 'Sealed'],
                            'rarities' => ['All', 'Alt Art', 'Secret Rare', 'Ultra Rare', 'Legendary', 'Limited'],
                            'franchises' => ['All', 'PokÃ©mon', 'Yu-Gi-Oh!', 'Magic: The Gathering', 'One Piece', 'Sports Cards'],
                            'brands' => ['All', 'PSA', 'BGS', 'CGC', 'Topps', 'Bandai', 'The PokÃ©mon Company', 'Wizards'],
                            'graded' => ['All', 'Graded', 'Ungraded'],
                            'availability' => ['All', 'In stock', '1-2 days', 'Pre-order'],
                            'seller_ratings' => ['All', '4.0+', '4.5+', '4.8+'],
                        ],
                        'spotlight_filters' => $spotlights['cards']['en'],
                    ],
                ],
                'visual' => [
                    'gradient' => 'from-[#1f3f74] via-[#0e1d35] to-[#0a1120]',
                    'accent' => '#f2cb70',
                    'label' => 'Slab & Sealed',
                    'finish' => 'Premium Card Flow',
                ],
            ],
            'figures' => [
                'slug' => 'figoures',
                'icon' => 'box',
                'sort_order' => 2,
                'translations' => [
                    'el' => [
                        'name' => 'Φιγούρες',
                        'tagline' => 'Premium display pieces, action figures και συλλεκτικά exclusives',
                        'short_description' => 'Premium φιγούρες και display collectibles.',
                        'description' => 'Σπάνιες φιγούρες, anime statues, Funko Pop και premium display pieces με ασφαλή escrow διαδικασία.',
                        'market_label' => 'Cardora Market',
                        'subcategories' => $subcategories['figures']['el'],
                        'product_types' => self::withAllOption($subcategories['figures']['el'], 'el'),
                        'filters' => [
                            'prices' => ['Όλες', 'Έως 50€', '50€ - 150€', '150€ - 500€', '500€ - 1.500€', '1.500€+'],
                            'conditions' => ['Όλες', 'New', 'Sealed', 'Open Box', 'Displayed', 'Used', 'Damaged'],
                            'rarities' => ['Όλες', 'Exclusive', 'Limited', 'Chase', 'Signed', 'Deluxe'],
                            'franchises' => ['Όλες', 'Marvel', 'DC', 'One Piece', 'Dragon Ball', 'Star Wars', 'Pokemon', 'Nintendo', 'PlayStation', 'Xbox', 'Amiibo', 'Warhammer', 'D&D', 'RPG'],
                            'brands' => ['Όλες', 'Funko', 'Hot Toys', 'Banpresto', 'Kotobukiya', 'Good Smile Company'],
                            'graded' => ['Όλες', 'Factory Sealed', 'Open Box'],
                            'availability' => ['Όλες', 'Άμεσα διαθέσιμο', '1-2 ημέρες', 'Προπαραγγελία'],
                            'seller_ratings' => ['Όλες', '4.0+', '4.5+', '4.8+'],
                        ],
                        'spotlight_filters' => $spotlights['figures']['el'],
                    ],
                    'en' => [
                        'name' => 'Figures',
                        'tagline' => 'Premium display pieces, action figures and collectible exclusives',
                        'short_description' => 'Premium figures and display collectibles.',
                        'description' => 'Rare figures, anime statues, Funko Pop and premium display pieces with a safer Cardora buying flow.',
                        'market_label' => 'Cardora Market',
                        'subcategories' => $subcategories['figures']['en'],
                        'product_types' => self::withAllOption($subcategories['figures']['en'], 'en'),
                        'filters' => [
                            'prices' => ['All', 'Up to €50', '€50 - €150', '€150 - €500', '€500 - €1,500', '€1,500+'],
                            'conditions' => ['All', 'New', 'Sealed', 'Open Box', 'Displayed', 'Used', 'Damaged'],
                            'rarities' => ['All', 'Exclusive', 'Limited', 'Chase', 'Signed', 'Deluxe'],
                            'franchises' => ['All', 'Marvel', 'DC', 'One Piece', 'Dragon Ball', 'Star Wars', 'Pokemon', 'Nintendo', 'PlayStation', 'Xbox', 'Amiibo', 'Warhammer', 'D&D', 'RPG'],
                            'brands' => ['All', 'Funko', 'Hot Toys', 'Banpresto', 'Kotobukiya', 'Good Smile Company'],
                            'graded' => ['All', 'Factory Sealed', 'Open Box'],
                            'availability' => ['All', 'In stock', '1-2 days', 'Pre-order'],
                            'seller_ratings' => ['All', '4.0+', '4.5+', '4.8+'],
                        ],
                        'spotlight_filters' => $spotlights['figures']['en'],
                    ],
                ],
                'visual' => [
                    'gradient' => 'from-[#5b1834] via-[#101a31] to-[#0a101c]',
                    'accent' => '#f2cb70',
                    'label' => 'Premium Display',
                    'finish' => 'Collector Figure Flow',
                ],
            ],
            'comics' => [
                'slug' => 'komik-vivlia',
                'icon' => 'book-open',
                'sort_order' => 3,
                'translations' => [
                    'el' => [
                        'name' => 'Κόμικ-Βιβλία',
                        'tagline' => 'Κόμικς, manga και premium εκδόσεις για σοβαρές συλλογές',
                        'short_description' => 'Κόμικς, manga και collector books.',
                        'description' => 'Κόμικς, graphic novels, manga, art books και συλλεκτικές εκδόσεις με καθαρά στοιχεία κατάστασης και αποστολής.',
                        'market_label' => 'Cardora Market',
                        'subcategories' => $subcategories['comics']['el'],
                        'product_types' => self::withAllOption($subcategories['comics']['el'], 'el'),
                        'filters' => [
                            'prices' => ['Όλες', 'Έως 50€', '50€ - 150€', '150€ - 500€', '500€ - 1.500€'],
                            'conditions' => ['Όλες', 'Mint', 'Near Mint', 'Excellent', 'Very Good', 'Sealed'],
                            'rarities' => ['Όλες', 'First Print', 'Variant Cover', 'Signed', 'Limited', 'Deluxe'],
                            'franchises' => ['Όλες', 'Marvel', 'DC', 'Berserk', 'Batman', 'Zelda'],
                            'brands' => ['Όλες', 'Marvel Comics', 'DC Comics', 'Dark Horse', 'Kodansha', 'VIZ'],
                            'graded' => ['Όλες', 'CGC Slab', 'Raw Copy'],
                            'availability' => ['Όλες', 'Άμεσα διαθέσιμο', '1-2 ημέρες'],
                            'seller_ratings' => ['Όλες', '4.0+', '4.5+', '4.8+'],
                        ],
                        'spotlight_filters' => $spotlights['comics']['el'],
                    ],
                    'en' => [
                        'name' => 'Comics-Books',
                        'tagline' => 'Comics, manga and premium editions for serious collections',
                        'short_description' => 'Comics, manga and collector books.',
                        'description' => 'Comics, graphic novels, manga, art books and collector editions with clear condition and shipping details.',
                        'market_label' => 'Cardora Market',
                        'subcategories' => $subcategories['comics']['en'],
                        'product_types' => self::withAllOption($subcategories['comics']['en'], 'en'),
                        'filters' => [
                            'prices' => ['All', 'Up to €50', '€50 - €150', '€150 - €500', '€500 - €1,500'],
                            'conditions' => ['All', 'Mint', 'Near Mint', 'Excellent', 'Very Good', 'Sealed'],
                            'rarities' => ['All', 'First Print', 'Variant Cover', 'Signed', 'Limited', 'Deluxe'],
                            'franchises' => ['All', 'Marvel', 'DC', 'Berserk', 'Batman', 'Zelda'],
                            'brands' => ['All', 'Marvel Comics', 'DC Comics', 'Dark Horse', 'Kodansha', 'VIZ'],
                            'graded' => ['All', 'CGC Slab', 'Raw Copy'],
                            'availability' => ['All', 'In stock', '1-2 days'],
                            'seller_ratings' => ['All', '4.0+', '4.5+', '4.8+'],
                        ],
                        'spotlight_filters' => $spotlights['comics']['en'],
                    ],
                ],
                'visual' => [
                    'gradient' => 'from-[#3e2a6f] via-[#121b33] to-[#0a101d]',
                    'accent' => '#f2cb70',
                    'label' => 'Print & Deluxe',
                    'finish' => 'Collector Reading Flow',
                ],
            ],
            'misc' => [
                'slug' => 'diafora',
                'icon' => 'package',
                'sort_order' => 4,
                'translations' => [
                    'el' => [
                        'name' => 'Διάφορα',
                        'tagline' => 'Αξεσουάρ, display items, merch και όλα τα premium extras',
                        'short_description' => 'Όλα τα υπόλοιπα premium συλλεκτικά.',
                        'description' => 'Για όλα τα συλλεκτικά που δεν χωρούν αλλού, με premium παρουσίαση, τεκμηρίωση αυθεντικότητας και ασφαλή πληρωμή.',
                        'market_label' => 'Cardora Market',
                        'subcategories' => $subcategories['misc']['el'],
                        'product_types' => self::withAllOption($subcategories['misc']['el'], 'el'),
                        'filters' => [
                            'prices' => ['Όλες', 'Έως 50€', '50€ - 150€', '150€ - 500€', '500€ - 1.500€'],
                            'conditions' => ['Όλες', 'Mint', 'Near Mint', 'Excellent', 'Sealed'],
                            'rarities' => ['Όλες', 'Limited', 'Convention', 'Anniversary', 'Collector Edition'],
                            'franchises' => ['Όλες', 'Nintendo', 'Star Wars', 'Pokémon', 'Marvel'],
                            'brands' => ['Όλες', 'Nintendo', 'LEGO', 'Loungefly', 'Hasbro', 'Ultra PRO'],
                            'graded' => ['Όλες', 'Authenticated', 'Standard'],
                            'availability' => ['Όλες', 'Άμεσα διαθέσιμο', '1-2 ημέρες'],
                            'seller_ratings' => ['Όλες', '4.0+', '4.5+', '4.8+'],
                        ],
                        'spotlight_filters' => $spotlights['misc']['el'],
                    ],
                    'en' => [
                        'name' => 'Misc',
                        'tagline' => 'Accessories, display items, merch and all the premium extras',
                        'short_description' => 'Everything else in premium collectibles.',
                        'description' => 'For everything collectible that does not fit elsewhere, with premium presentation, authenticity details and protected payment.',
                        'market_label' => 'Cardora Market',
                        'subcategories' => $subcategories['misc']['en'],
                        'product_types' => self::withAllOption($subcategories['misc']['en'], 'en'),
                        'filters' => [
                            'prices' => ['All', 'Up to €50', '€50 - €150', '€150 - €500', '€500 - €1,500'],
                            'conditions' => ['All', 'Mint', 'Near Mint', 'Excellent', 'Sealed'],
                            'rarities' => ['All', 'Limited', 'Convention', 'Anniversary', 'Collector Edition'],
                            'franchises' => ['All', 'Nintendo', 'Star Wars', 'PokÃ©mon', 'Marvel'],
                            'brands' => ['All', 'Nintendo', 'LEGO', 'Loungefly', 'Hasbro', 'Ultra PRO'],
                            'graded' => ['All', 'Authenticated', 'Standard'],
                            'availability' => ['All', 'In stock', '1-2 days'],
                            'seller_ratings' => ['All', '4.0+', '4.5+', '4.8+'],
                        ],
                        'spotlight_filters' => $spotlights['misc']['en'],
                    ],
                ],
                'visual' => [
                    'gradient' => 'from-[#24514f] via-[#112031] to-[#0a111d]',
                    'accent' => '#f2cb70',
                    'label' => 'Rare Extras',
                    'finish' => 'Premium Odds & Ends',
                ],
            ],
        ];
    }

    public static function categorySubcategories(): array
    {
        return [
            'cards' => [
                'el' => [
                    'Μεμονωμένες Κάρτες',
                    'Πακέτα Booster',
                    'Κουτιά Booster',
                    'Elite Trainer Boxes',
                    'Starter / Structure Cards',
                    'Bundles / Gift Sets',
                    'Promo Κάρτες',
                    'Πιστοποιημένες Κάρτες',
                    'Hobby Boxes',
                    'Retail Boxes',
                    'Blaster Boxes',
                    'Mega Boxes',
                    'Άλλο',
                ],
                'en' => [
                    'Single Cards',
                    'Booster Pack',
                    'Booster Box',
                    'Elite Trainer Box',
                    'Starter / Structure Cards',
                    'Bundles / Gift Sets',
                    'Promo Cards',
                    'Graded Cards',
                    'Hobby Boxes',
                    'Retail Boxes',
                    'Blaster Boxes',
                    'Mega Boxes',
                    'Other',
                ],
            ],
            'figures' => [
                'el' => [
                    'Action Figures',
                    'Αγάλματα',
                    'Scale Figures',
                    'Miniatures',
                    'Funko Pop',
                    'Chibi Figures',
                    'Diorama Figures',
                    '3D Print',
                    'Άλλο',
                ],
                'en' => [
                    'Action Figures',
                    'Statues',
                    'Scale Figures',
                    'Miniatures',
                    'Funko Pop',
                    'Chibi Figures',
                    'Diorama Figures',
                    '3D Print',
                    'Other',
                ],
            ],
            'comics' => [
                'el' => [
                    'Κόμικς',
                    'Graphic Novels',
                    'Manga',
                    'Trade Paperbacks',
                    'Hardcover Εκδόσεις',
                    'Omnibus / Συλλογές',
                    'Art Books',
                    'Light Novels',
                    'Μυθιστορήματα',
                    'Οδηγοί / Εγκυκλοπαίδειες',
                    'Sketchbooks / Εκδόσεις Δημιουργών',
                    'Περιοδικά',
                    'Limited / Collector Editions',
                    'Άλλο',
                ],
                'en' => [
                    'Comic Books',
                    'Graphic Novels',
                    'Manga',
                    'Trade Paperbacks',
                    'Hardcover Editions',
                    'Omnibus / Collections',
                    'Art Books',
                    'Light Novels',
                    'Novels (Fiction)',
                    'Guidebooks / Encyclopedias',
                    'Sketchbooks / Creator Editions',
                    'Magazines',
                    'Limited / Collector Editions',
                    'Other',
                ],
            ],
            'misc' => [
                'el' => [
                    'Αξεσουάρ',
                    'Αποθήκευση & Προστασία',
                    'Ένδυση',
                    'Σπίτι & Διακόσμηση',
                    'Merchandise',
                    'Παιχνίδια & Παζλ',
                    'Λούτρινα',
                    'Tech Accessories',
                    'Είδη Παρουσίασης',
                    'Υπογεγραμμένα Αντικείμενα',
                    'Άλλο',
                ],
                'en' => [
                    'Accessories',
                    'Storage & Protection',
                    'Apparel',
                    'Home & Decor',
                    'Merchandise',
                    'Games & Puzzles',
                    'Plush',
                    'Tech Accessories',
                    'Display Items',
                    'Signed Items',
                    'Other',
                ],
            ],
        ];
    }

    public static function categorySpotlightFilters(): array
    {
        return [
            'cards' => [
                'el' => [
                    'Μεμονωμένες Κάρτες',
                    'Πακέτα Booster',
                    'Κουτιά Booster',
                    'Elite Trainer Boxes',
                    'Promo Κάρτες',
                    'Πιστοποιημένες Κάρτες',
                ],
                'en' => [
                    'Single Cards',
                    'Booster Pack',
                    'Booster Box',
                    'Elite Trainer Box',
                    'Promo Cards',
                    'Graded Cards',
                ],
            ],
            'figures' => [
                'el' => [
                    'Action Figures',
                    'Miniatures',
                    'Αγάλματα',
                    'Scale Figures',
                    'Funko Pop',
                    'Diorama Figures',
                ],
                'en' => [
                    'Action Figures',
                    'Miniatures',
                    'Statues',
                    'Scale Figures',
                    'Funko Pop',
                    'Diorama Figures',
                ],
            ],
            'comics' => [
                'el' => [
                    'Κόμικς',
                    'Graphic Novels',
                    'Manga',
                    'Omnibus / Συλλογές',
                    'Art Books',
                    'Limited / Collector Editions',
                ],
                'en' => [
                    'Comic Books',
                    'Graphic Novels',
                    'Manga',
                    'Omnibus / Collections',
                    'Art Books',
                    'Limited / Collector Editions',
                ],
            ],
            'misc' => [
                'el' => [
                    'Αξεσουάρ',
                    'Αποθήκευση & Προστασία',
                    'Merchandise',
                    'Είδη Παρουσίασης',
                    'Υπογεγραμμένα Αντικείμενα',
                ],
                'en' => [
                    'Accessories',
                    'Storage & Protection',
                    'Merchandise',
                    'Display Items',
                    'Signed Items',
                ],
            ],
        ];
    }

    public static function localeValue(array $translatable, string $locale, string $key, mixed $fallback = null): mixed
    {
        return Arr::get($translatable[$locale] ?? [], $key)
            ?? Arr::get($translatable['el'] ?? [], $key)
            ?? $fallback;
    }

    protected static function withAllOption(array $items, string $locale): array
    {
        return array_merge([$locale === 'en' ? 'All' : 'Όλες'], $items);
    }
}
