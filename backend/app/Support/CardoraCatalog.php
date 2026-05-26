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
                        'name' => 'ÎšÎ¬ÏÏ„ÎµÏ‚',
                        'tagline' => 'TCG, graded slabs, sealed Ï€ÏÎ¿ÏŠÏŒÎ½Ï„Î± ÎºÎ±Î¹ premium Î¼Î¿Î½Î­Ï‚ ÎºÎ¬ÏÏ„ÎµÏ‚',
                        'short_description' => 'Î— Î²Î±ÏƒÎ¹ÎºÎ® premium ÎºÎ±Ï„Î·Î³Î¿ÏÎ¯Î± Î³Î¹Î± trading cards.',
                        'description' => 'Pok?mon, Yu-Gi-Oh!, Magic, One Piece ??? sports cards ? ?? verified ??????? ??? ?????? ???????.',
                        'market_label' => 'Cardora Market',
                        'subcategories' => $subcategories['cards']['el'],
                        'product_types' => self::withAllOption($subcategories['cards']['el'], 'el'),
                        'filters' => [
                            'prices' => ['ÎŒÎ»ÎµÏ‚', 'ÎˆÏ‰Ï‚ 50â‚¬', '50â‚¬ - 150â‚¬', '150â‚¬ - 500â‚¬', '500â‚¬ - 1.500â‚¬', '1.500â‚¬+'],
                            'conditions' => ['ÎŒÎ»ÎµÏ‚', 'Mint', 'Near Mint', 'Excellent', 'Very Good', 'Sealed'],
                            'rarities' => ['ÎŒÎ»ÎµÏ‚', 'Alt Art', 'Secret Rare', 'Ultra Rare', 'Legendary', 'Limited'],
                            'franchises' => ['ÎŒÎ»ÎµÏ‚', 'PokÃ©mon', 'Yu-Gi-Oh!', 'Magic: The Gathering', 'One Piece', 'Sports Cards'],
                            'brands' => ['ÎŒÎ»ÎµÏ‚', 'PSA', 'BGS', 'CGC', 'Topps', 'Bandai', 'The PokÃ©mon Company', 'Wizards'],
                            'graded' => ['ÎŒÎ»ÎµÏ‚', 'Graded', 'Ungraded'],
                            'availability' => ['ÎŒÎ»ÎµÏ‚', 'Î†Î¼ÎµÏƒÎ± Î´Î¹Î±Î¸Î­ÏƒÎ¹Î¼Î¿', '1-2 Î·Î¼Î­ÏÎµÏ‚', 'Î ÏÎ¿Ï€Î±ÏÎ±Î³Î³ÎµÎ»Î¯Î±'],
                            'seller_ratings' => ['ÎŒÎ»ÎµÏ‚', '4.0+', '4.5+', '4.8+'],
                        ],
                        'spotlight_filters' => $spotlights['cards']['el'],
                    ],
                    'en' => [
                        'name' => 'Cards',
                        'tagline' => 'TCG, graded slabs, sealed products and premium single cards',
                        'short_description' => 'The main premium category for trading cards.',
                        'description' => 'Cardoraâ€™s strongest category, with PokÃ©mon, Yu-Gi-Oh!, Magic, One Piece and sports cards for serious collectors.',
                        'market_label' => 'Cardora Market',
                        'subcategories' => $subcategories['cards']['en'],
                        'product_types' => self::withAllOption($subcategories['cards']['en'], 'en'),
                        'filters' => [
                            'prices' => ['All', 'Up to â‚¬50', 'â‚¬50 - â‚¬150', 'â‚¬150 - â‚¬500', 'â‚¬500 - â‚¬1,500', 'â‚¬1,500+'],
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
                        'name' => 'Î¦Î¹Î³Î¿ÏÏÎµÏ‚',
                        'tagline' => 'Premium display pieces, action figures ÎºÎ±Î¹ ÏƒÏ…Î»Î»ÎµÎºÏ„Î¹ÎºÎ¬ exclusives',
                        'short_description' => 'Premium Ï†Î¹Î³Î¿ÏÏÎµÏ‚ ÎºÎ±Î¹ display collectibles.',
                        'description' => 'Anime statues, Funko Pop ??? premium display pieces ? ?? verified ??????? ??? ?????? ???????',
                        'market_label' => 'Cardora Market',
                        'subcategories' => $subcategories['figures']['el'],
                        'product_types' => self::withAllOption($subcategories['figures']['el'], 'el'),
                        'filters' => [
                            'prices' => ['ÎŒÎ»ÎµÏ‚', 'ÎˆÏ‰Ï‚ 50â‚¬', '50â‚¬ - 150â‚¬', '150â‚¬ - 500â‚¬', '500â‚¬ - 1.500â‚¬', '1.500â‚¬+'],
                            'conditions' => ['ÎŒÎ»ÎµÏ‚', 'New', 'Sealed', 'Open Box', 'Displayed', 'Used', 'Damaged'],
                            'rarities' => ['ÎŒÎ»ÎµÏ‚', 'Exclusive', 'Limited', 'Chase', 'Signed', 'Deluxe'],
                            'franchises' => ['ÎŒÎ»ÎµÏ‚', 'Marvel', 'DC', 'One Piece', 'Dragon Ball', 'Star Wars', 'Pokemon', 'Nintendo', 'PlayStation', 'Xbox', 'Amiibo', 'Warhammer', 'D&D', 'RPG'],
                            'brands' => ['ÎŒÎ»ÎµÏ‚', 'Funko', 'Hot Toys', 'Banpresto', 'Kotobukiya', 'Good Smile Company'],
                            'graded' => ['ÎŒÎ»ÎµÏ‚', 'Factory Sealed', 'Open Box'],
                            'availability' => ['ÎŒÎ»ÎµÏ‚', 'Î†Î¼ÎµÏƒÎ± Î´Î¹Î±Î¸Î­ÏƒÎ¹Î¼Î¿', '1-2 Î·Î¼Î­ÏÎµÏ‚', 'Î ÏÎ¿Ï€Î±ÏÎ±Î³Î³ÎµÎ»Î¯Î±'],
                            'seller_ratings' => ['ÎŒÎ»ÎµÏ‚', '4.0+', '4.5+', '4.8+'],
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
                            'prices' => ['All', 'Up to â‚¬50', 'â‚¬50 - â‚¬150', 'â‚¬150 - â‚¬500', 'â‚¬500 - â‚¬1,500', 'â‚¬1,500+'],
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
                        'name' => 'ÎšÏŒÎ¼Î¹Îº-Î’Î¹Î²Î»Î¯Î±',
                        'tagline' => 'ÎšÏŒÎ¼Î¹ÎºÏ‚, manga ÎºÎ±Î¹ premium ÎµÎºÎ´ÏŒÏƒÎµÎ¹Ï‚ Î³Î¹Î± ÏƒÎ¿Î²Î±ÏÎ­Ï‚ ÏƒÏ…Î»Î»Î¿Î³Î­Ï‚',
                        'short_description' => 'ÎšÏŒÎ¼Î¹ÎºÏ‚, manga ÎºÎ±Î¹ collector books.',
                        'description' => '??????, manga, graphic novels ??? ??????????? ???????? ? ?? verified ??????? ??? ?????? ???????',
                        'market_label' => 'Cardora Market',
                        'subcategories' => $subcategories['comics']['el'],
                        'product_types' => self::withAllOption($subcategories['comics']['el'], 'el'),
                        'filters' => [
                            'prices' => ['ÎŒÎ»ÎµÏ‚', 'ÎˆÏ‰Ï‚ 50â‚¬', '50â‚¬ - 150â‚¬', '150â‚¬ - 500â‚¬', '500â‚¬ - 1.500â‚¬'],
                            'conditions' => ['ÎŒÎ»ÎµÏ‚', 'Mint', 'Near Mint', 'Excellent', 'Very Good', 'Sealed'],
                            'rarities' => ['ÎŒÎ»ÎµÏ‚', 'First Print', 'Variant Cover', 'Signed', 'Limited', 'Deluxe'],
                            'franchises' => ['ÎŒÎ»ÎµÏ‚', 'Marvel', 'DC', 'Berserk', 'Batman', 'Zelda'],
                            'brands' => ['ÎŒÎ»ÎµÏ‚', 'Marvel Comics', 'DC Comics', 'Dark Horse', 'Kodansha', 'VIZ'],
                            'graded' => ['ÎŒÎ»ÎµÏ‚', 'CGC Slab', 'Raw Copy'],
                            'availability' => ['ÎŒÎ»ÎµÏ‚', 'Î†Î¼ÎµÏƒÎ± Î´Î¹Î±Î¸Î­ÏƒÎ¹Î¼Î¿', '1-2 Î·Î¼Î­ÏÎµÏ‚'],
                            'seller_ratings' => ['ÎŒÎ»ÎµÏ‚', '4.0+', '4.5+', '4.8+'],
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
                            'prices' => ['All', 'Up to â‚¬50', 'â‚¬50 - â‚¬150', 'â‚¬150 - â‚¬500', 'â‚¬500 - â‚¬1,500'],
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
                        'name' => 'Î”Î¹Î¬Ï†Î¿ÏÎ±',
                        'tagline' => 'Î‘Î¾ÎµÏƒÎ¿Ï…Î¬Ï, display items, merch ÎºÎ±Î¹ ÏŒÎ»Î± Ï„Î± premium extras',
                        'short_description' => 'ÎŒÎ»Î± Ï„Î± Ï…Ï€ÏŒÎ»Î¿Î¹Ï€Î± premium ÏƒÏ…Î»Î»ÎµÎºÏ„Î¹ÎºÎ¬.',
                        'description' => '??? ?????? ?????????? ??? ??? ??????? ?? ??? ???? ?????????',
                        'market_label' => 'Cardora Market',
                        'subcategories' => $subcategories['misc']['el'],
                        'product_types' => self::withAllOption($subcategories['misc']['el'], 'el'),
                        'filters' => [
                            'prices' => ['ÎŒÎ»ÎµÏ‚', 'ÎˆÏ‰Ï‚ 50â‚¬', '50â‚¬ - 150â‚¬', '150â‚¬ - 500â‚¬', '500â‚¬ - 1.500â‚¬'],
                            'conditions' => ['ÎŒÎ»ÎµÏ‚', 'Mint', 'Near Mint', 'Excellent', 'Sealed'],
                            'rarities' => ['ÎŒÎ»ÎµÏ‚', 'Limited', 'Convention', 'Anniversary', 'Collector Edition'],
                            'franchises' => ['ÎŒÎ»ÎµÏ‚', 'Nintendo', 'Star Wars', 'PokÃ©mon', 'Marvel'],
                            'brands' => ['ÎŒÎ»ÎµÏ‚', 'Nintendo', 'LEGO', 'Loungefly', 'Hasbro', 'Ultra PRO'],
                            'graded' => ['ÎŒÎ»ÎµÏ‚', 'Authenticated', 'Standard'],
                            'availability' => ['ÎŒÎ»ÎµÏ‚', 'Î†Î¼ÎµÏƒÎ± Î´Î¹Î±Î¸Î­ÏƒÎ¹Î¼Î¿', '1-2 Î·Î¼Î­ÏÎµÏ‚'],
                            'seller_ratings' => ['ÎŒÎ»ÎµÏ‚', '4.0+', '4.5+', '4.8+'],
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
                            'prices' => ['All', 'Up to â‚¬50', 'â‚¬50 - â‚¬150', 'â‚¬150 - â‚¬500', 'â‚¬500 - â‚¬1,500'],
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
                    'ÎœÎµÎ¼Î¿Î½Ï‰Î¼Î­Î½ÎµÏ‚ ÎšÎ¬ÏÏ„ÎµÏ‚',
                    'Î Î±ÎºÎ­Ï„Î± Booster',
                    'ÎšÎ¿Ï…Ï„Î¹Î¬ Booster',
                    'Elite Trainer Boxes',
                    'Starter / Structure Cards',
                    'Bundles / Gift Sets',
                    'Promo ÎšÎ¬ÏÏ„ÎµÏ‚',
                    'Î Î¹ÏƒÏ„Î¿Ï€Î¿Î¹Î·Î¼Î­Î½ÎµÏ‚ ÎšÎ¬ÏÏ„ÎµÏ‚',
                    'Hobby Boxes',
                    'Retail Boxes',
                    'Blaster Boxes',
                    'Mega Boxes',
                    'Î†Î»Î»Î¿',
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
                    'Î‘Î³Î¬Î»Î¼Î±Ï„Î±',
                    'Scale Figures',
                    'Miniatures',
                    'Funko Pop',
                    'Chibi Figures',
                    'Diorama Figures',
                    '3D Print',
                    'Î†Î»Î»Î¿',
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
                    'ÎšÏŒÎ¼Î¹ÎºÏ‚',
                    'Graphic Novels',
                    'Manga',
                    'Trade Paperbacks',
                    'Hardcover Î•ÎºÎ´ÏŒÏƒÎµÎ¹Ï‚',
                    'Omnibus / Î£Ï…Î»Î»Î¿Î³Î­Ï‚',
                    'Art Books',
                    'Light Novels',
                    'ÎœÏ…Î¸Î¹ÏƒÏ„Î¿ÏÎ®Î¼Î±Ï„Î±',
                    'ÎŸÎ´Î·Î³Î¿Î¯ / Î•Î³ÎºÏ…ÎºÎ»Î¿Ï€Î±Î¯Î´ÎµÎ¹ÎµÏ‚',
                    'Sketchbooks / Î•ÎºÎ´ÏŒÏƒÎµÎ¹Ï‚ Î”Î·Î¼Î¹Î¿Ï…ÏÎ³ÏŽÎ½',
                    'Î ÎµÏÎ¹Î¿Î´Î¹ÎºÎ¬',
                    'Limited / Collector Editions',
                    'Î†Î»Î»Î¿',
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
                    'Î‘Î¾ÎµÏƒÎ¿Ï…Î¬Ï',
                    'Î‘Ï€Î¿Î¸Î®ÎºÎµÏ…ÏƒÎ· & Î ÏÎ¿ÏƒÏ„Î±ÏƒÎ¯Î±',
                    'ÎˆÎ½Î´Ï…ÏƒÎ·',
                    'Î£Ï€Î¯Ï„Î¹ & Î”Î¹Î±ÎºÏŒÏƒÎ¼Î·ÏƒÎ·',
                    'Merchandise',
                    'Î Î±Î¹Ï‡Î½Î¯Î´Î¹Î± & Î Î±Î¶Î»',
                    'Î›Î¿ÏÏ„ÏÎ¹Î½Î±',
                    'Tech Accessories',
                    'Î•Î¯Î´Î· Î Î±ÏÎ¿Ï…ÏƒÎ¯Î±ÏƒÎ·Ï‚',
                    'Î¥Ï€Î¿Î³ÎµÎ³ÏÎ±Î¼Î¼Î­Î½Î± Î‘Î½Ï„Î¹ÎºÎµÎ¯Î¼ÎµÎ½Î±',
                    'Î†Î»Î»Î¿',
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
                    'ÎœÎµÎ¼Î¿Î½Ï‰Î¼Î­Î½ÎµÏ‚ ÎšÎ¬ÏÏ„ÎµÏ‚',
                    'Î Î±ÎºÎ­Ï„Î± Booster',
                    'ÎšÎ¿Ï…Ï„Î¹Î¬ Booster',
                    'Elite Trainer Boxes',
                    'Promo ÎšÎ¬ÏÏ„ÎµÏ‚',
                    'Î Î¹ÏƒÏ„Î¿Ï€Î¿Î¹Î·Î¼Î­Î½ÎµÏ‚ ÎšÎ¬ÏÏ„ÎµÏ‚',
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
                    'Î‘Î³Î¬Î»Î¼Î±Ï„Î±',
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
                    'ÎšÏŒÎ¼Î¹ÎºÏ‚',
                    'Graphic Novels',
                    'Manga',
                    'Omnibus / Î£Ï…Î»Î»Î¿Î³Î­Ï‚',
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
                    'Î‘Î¾ÎµÏƒÎ¿Ï…Î¬Ï',
                    'Î‘Ï€Î¿Î¸Î®ÎºÎµÏ…ÏƒÎ· & Î ÏÎ¿ÏƒÏ„Î±ÏƒÎ¯Î±',
                    'Merchandise',
                    'Î•Î¯Î´Î· Î Î±ÏÎ¿Ï…ÏƒÎ¯Î±ÏƒÎ·Ï‚',
                    'Î¥Ï€Î¿Î³ÎµÎ³ÏÎ±Î¼Î¼Î­Î½Î± Î‘Î½Ï„Î¹ÎºÎµÎ¯Î¼ÎµÎ½Î±',
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
        return array_merge([$locale === 'en' ? 'All' : 'ÎŒÎ»ÎµÏ‚'], $items);
    }
}
