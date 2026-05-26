<?php

namespace Database\Seeders;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\CategoryTaxonomy;
use App\Models\CollectionEntry;
use App\Models\Conversation;
use App\Models\DrawCampaign;
use App\Models\DrawEntry;
use App\Models\EscrowTransaction;
use App\Models\Favorite;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProfileLike;
use App\Models\Review;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\VerificationSubmission;
use App\Support\CardoraCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CardoraMarketplaceSeeder extends Seeder
{
    public function run(): void
    {
        $categories = $this->seedCategories();
        $this->seedCategoryTaxonomies($categories);
        $users = $this->seedUsers();
        $listings = $this->seedProductsAndListings($categories, $users);
        $orders = $this->seedOrders($users, $listings);

        $this->seedConversations($users, $listings);
        $this->seedFavoritesAndCart($users, $listings);
        $this->seedBlog($users);
        $this->seedDraws($users, $listings, $orders);
        $this->seedCollectionsAndLikes($users, $listings);
        $this->seedVerification($users);
        $this->seedSupportAndNotifications($users, $orders);
        $this->seedReviews($users, $listings, $orders);
    }

    protected function seedCategories(): array
    {
        $categories = [];

        foreach (CardoraCatalog::categories() as $key => $definition) {
            $category = Category::updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => Arr::get($definition, 'translations.el.name'),
                    'short_description' => Arr::get($definition, 'translations.el.short_description'),
                    'description' => Arr::get($definition, 'translations.el.description'),
                    'icon' => $definition['icon'],
                    'status' => 'active',
                    'sort_order' => $definition['sort_order'],
                    'metadata' => [
                        'frontend_key' => $key,
                        'translations' => $definition['translations'],
                        'visual' => $definition['visual'],
                    ],
                ]
            );

            $categories[$key] = $category;
        }

        return $categories;
    }

    protected function seedCategoryTaxonomies(array $categories): void
    {
        $subcategoryDefinitions = CardoraCatalog::categorySubcategories();

        foreach ($subcategoryDefinitions as $categoryKey => $translations) {
            $category = $categories[$categoryKey] ?? null;

            if (! $category) {
                continue;
            }

            $englishItems = $translations['en'] ?? [];
            $greekItems = $translations['el'] ?? [];

            foreach ($englishItems as $index => $englishName) {
                $greekName = $greekItems[$index] ?? $englishName;

                CategoryTaxonomy::updateOrCreate(
                    [
                        'category_id' => $category->getKey(),
                        'taxonomy_type' => 'item_type',
                        'slug' => Str::slug($englishName),
                    ],
                    [
                        'parent_id' => null,
                        'name' => $greekName,
                        'description' => null,
                        'icon' => null,
                        'status' => 'active',
                        'sort_order' => $index + 1,
                        'metadata' => [
                            'translations' => [
                                'el' => ['name' => $greekName],
                                'en' => ['name' => $englishName],
                            ],
                            'system_seeded' => true,
                            'source' => 'cardora_catalog',
                        ],
                    ]
                );
            }
        }
    }

    protected function seedUsers(): array
    {
        $users = [];

        foreach ($this->userData() as $key => $data) {
            $users[$key] = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'display_name' => $data['display_name'],
                    'handle' => $data['handle'],
                    'phone' => $data['phone'],
                    'city' => $data['city'],
                    'bio' => $data['bio'],
                    'collector_tagline' => $data['collector_tagline'],
                    'profile_visibility' => 'public',
                    'avatar_url' => null,
                    'profile_cover' => [
                        'metadata' => $data['profile_metadata'],
                    ],
                    'locale' => 'el',
                    'favorite_categories' => $data['favorite_categories'],
                    'trust_status' => $data['trust_status'],
                    'rating' => $data['rating'],
                    'sales_count' => $data['sales_count'],
                    'purchase_count' => $data['purchase_count'],
                    'is_verified_seller' => $data['is_verified_seller'],
                    'password' => Hash::make('Cardora123!'),
                ]
            );
        }

        return $users;
    }

    protected function seedProductsAndListings(array $categories, array $users): array
    {
        $listings = [];

        foreach ($this->listingData() as $key => $data) {
            $category = $categories[$data['category_key']];
            $seller = $users[$data['seller_key']];

            $product = Product::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'category_id' => $category->getKey(),
                    'title' => $data['title'],
                    'sku' => $data['sku'],
                    'subtitle' => $data['subtitle'],
                    'franchise' => $data['franchise'],
                    'series' => $data['series'],
                    'brand' => $data['brand'],
                    'year' => $data['year'],
                    'language' => $data['language'],
                    'set_name' => $data['set_name'],
                    'item_number' => $data['item_number'],
                    'description' => $data['translations']['el']['description'],
                    'product_type' => $data['product_type'],
                    'specifications' => $data['specifications'],
                    'tags' => $data['tags'],
                    'media' => $data['media'],
                    'authenticity_notes' => $data['translations']['el']['authenticity'],
                    'is_authenticated' => true,
                    'is_lot' => $data['is_lot'] ?? false,
                    'lot_configuration' => $data['lot_configuration'] ?? null,
                    'metadata' => [
                        'translations' => $data['translations'],
                        'visual' => $data['visual'],
                    ],
                ]
            );

            $listing = Listing::updateOrCreate(
                ['product_id' => $product->getKey(), 'seller_id' => $seller->getKey()],
                [
                    'category_id' => $category->getKey(),
                    'title_snapshot' => $data['title'],
                    'price' => $data['price'],
                    'old_price' => $data['old_price'] ?? null,
                    'minimum_offer' => $data['minimum_offer'] ?? null,
                    'quantity' => $data['quantity'],
                    'available_quantity' => $data['available_quantity'],
                    'condition' => $data['condition'],
                    'rarity' => $data['rarity'],
                    'status' => $data['status'],
                    'sale_format' => $data['sale_format'],
                    'shipping_cost' => $data['shipping_cost'],
                    'shipping_profile' => $data['shipping_profile'] ?? 'premium_protected',
                    'shipping_methods' => ['Box Now', 'Courier'],
                    'dispatch_time' => $data['dispatch_time'] ?? '1-2 ημέρες',
                    'packaging_notes' => $data['translations']['el']['shipping_info'],
                    'availability' => $data['availability'],
                    'accept_offers' => $data['accept_offers'] ?? false,
                    'is_featured' => $data['is_featured'] ?? false,
                    'auction_settings' => $data['auction_settings'] ?? null,
                    'starting_bid' => $data['starting_bid'] ?? null,
                    'current_bid' => $data['current_bid'] ?? null,
                    'reserve_price' => $data['reserve_price'] ?? null,
                    'bid_increment' => $data['bid_increment'] ?? null,
                    'buyout_price' => $data['buyout_price'] ?? null,
                    'auction_starts_at' => $data['auction_starts_at'] ?? null,
                    'auction_ends_at' => $data['auction_ends_at'] ?? null,
                    'lot_snapshot' => $data['lot_configuration'] ?? null,
                    'published_at' => now()->subDays($data['published_days_ago']),
                    'attributes' => $data['attributes'],
                    'compliance_flags' => ['moderated', 'photos_reviewed'],
                ]
            );

            $listings[$key] = $listing;
        }

        return $listings;
    }

    protected function seedOrders(array $users, array $listings): array
    {
        $orders = [];

        $orders['hot_toys'] = Order::updateOrCreate(
            ['order_number' => 'CDR-202603-0001'],
            [
                'buyer_id' => $users['andreas']->getKey(),
                'seller_id' => $users['eleni']->getKey(),
                'status' => 'completed',
                'escrow_status' => 'released',
                'subtotal' => 415,
                'shipping_total' => 12,
                'service_fee' => 16.60,
                'total' => 443.60,
                'currency' => 'EUR',
                'payment_method' => 'cardora_protected_payment',
                'shipping_address' => ['city' => 'Αθήνα', 'postal_code' => '11523'],
                'billing_address' => ['city' => 'Αθήνα', 'postal_code' => '11523'],
                'tracking_number' => 'GR123456789',
                'placed_at' => now()->subDays(8),
                'completed_at' => now()->subDays(4),
            ]
        );

        OrderItem::updateOrCreate(
            ['order_id' => $orders['hot_toys']->getKey(), 'listing_id' => $listings['hot_toys_spiderman']->getKey()],
            [
                'product_id' => $listings['hot_toys_spiderman']->product_id,
                'title_snapshot' => $listings['hot_toys_spiderman']->title_snapshot,
                'unit_price' => 415,
                'quantity' => 1,
                'condition_snapshot' => 'Mint',
                'metadata' => ['sale_format' => 'fixed_price'],
            ]
        );

        EscrowTransaction::updateOrCreate(
            ['order_id' => $orders['hot_toys']->getKey()],
            [
                'buyer_id' => $users['andreas']->getKey(),
                'seller_id' => $users['eleni']->getKey(),
                'amount' => 443.60,
                'currency' => 'EUR',
                'status' => 'released',
                'held_at' => now()->subDays(8),
                'released_at' => now()->subDays(4),
            ]
        );

        $orders['berserk'] = Order::updateOrCreate(
            ['order_number' => 'CDR-202603-0002'],
            [
                'buyer_id' => $users['panos']->getKey(),
                'seller_id' => $users['maria']->getKey(),
                'status' => 'paid',
                'escrow_status' => 'held',
                'subtotal' => 189,
                'shipping_total' => 7,
                'service_fee' => 7.56,
                'total' => 203.56,
                'currency' => 'EUR',
                'payment_method' => 'paypal',
                'shipping_address' => ['city' => 'Θεσσαλονίκη', 'postal_code' => '54622'],
                'billing_address' => ['city' => 'Θεσσαλονίκη', 'postal_code' => '54622'],
                'tracking_number' => null,
                'placed_at' => now()->subDays(2),
            ]
        );

        OrderItem::updateOrCreate(
            ['order_id' => $orders['berserk']->getKey(), 'listing_id' => $listings['berserk_deluxe']->getKey()],
            [
                'product_id' => $listings['berserk_deluxe']->product_id,
                'title_snapshot' => $listings['berserk_deluxe']->title_snapshot,
                'unit_price' => 189,
                'quantity' => 1,
                'condition_snapshot' => 'Near Mint',
                'metadata' => ['sale_format' => 'fixed_price'],
            ]
        );

        EscrowTransaction::updateOrCreate(
            ['order_id' => $orders['berserk']->getKey()],
            [
                'buyer_id' => $users['panos']->getKey(),
                'seller_id' => $users['maria']->getKey(),
                'amount' => 203.56,
                'currency' => 'EUR',
                'status' => 'held',
                'held_at' => now()->subDays(2),
            ]
        );

        return $orders;
    }

    protected function seedConversations(array $users, array $listings): void
    {
        $conversation = Conversation::updateOrCreate(
            [
                'listing_id' => $listings['lugia_auction']->getKey(),
                'buyer_id' => $users['andreas']->getKey(),
                'seller_id' => $users['nikos']->getKey(),
            ],
            [
                'status' => 'active',
                'last_message_at' => now()->subHours(6),
                'metadata' => ['topic' => 'auction_question'],
            ]
        );

        Message::updateOrCreate(
            ['conversation_id' => $conversation->getKey(), 'sender_id' => $users['andreas']->getKey(), 'body' => 'Καλησπέρα, το reserve έχει ήδη καλυφθεί ή θα γίνει reveal μόνο στο τέλος;'],
            ['created_at' => now()->subHours(8), 'updated_at' => now()->subHours(8)]
        );

        Message::updateOrCreate(
            ['conversation_id' => $conversation->getKey(), 'sender_id' => $users['nikos']->getKey(), 'body' => 'Καλησπέρα, το reserve παραμένει private αλλά η αγγελία είναι πολύ κοντά στο threshold.'],
            ['created_at' => now()->subHours(6), 'updated_at' => now()->subHours(6)]
        );
    }

    protected function seedFavoritesAndCart(array $users, array $listings): void
    {
        Favorite::firstOrCreate([
            'user_id' => $users['andreas']->getKey(),
            'listing_id' => $listings['lugia_auction']->getKey(),
        ]);

        Favorite::firstOrCreate([
            'user_id' => $users['andreas']->getKey(),
            'listing_id' => $listings['batman_adventures']->getKey(),
        ]);

        CartItem::updateOrCreate(
            ['user_id' => $users['andreas']->getKey(), 'listing_id' => $listings['pokemon_151_box']->getKey()],
            ['quantity' => 1]
        );

        CartItem::updateOrCreate(
            ['user_id' => $users['andreas']->getKey(), 'listing_id' => $listings['pokemon_loot_lot']->getKey()],
            ['quantity' => 1]
        );
    }

    protected function seedBlog(array $users): void
    {
        $categories = [
            'trust' => BlogCategory::updateOrCreate(['slug' => 'trust-safety'], ['name' => 'Trust & Safety', 'description' => 'Escrow, verification and protected commerce.', 'sort_order' => 1]),
            'cards' => BlogCategory::updateOrCreate(['slug' => 'cards-guides'], ['name' => 'Cards Guides', 'description' => 'Premium card collecting guides.', 'sort_order' => 2]),
            'selling' => BlogCategory::updateOrCreate(['slug' => 'selling-playbook'], ['name' => 'Selling Playbook', 'description' => 'How to create stronger listings.', 'sort_order' => 3]),
        ];

        foreach ($this->blogData() as $post) {
            BlogPost::updateOrCreate(
                ['slug' => $post['slug']],
                [
                    'blog_category_id' => $categories[$post['category_key']]->getKey(),
                    'author_id' => $users[$post['author_key']]->getKey(),
                    'title' => $post['title'],
                    'excerpt' => $post['excerpt'],
                    'content' => $post['content'],
                    'cover_media' => $post['cover_media'],
                    'status' => 'published',
                    'published_at' => now()->subDays($post['published_days_ago']),
                    'tags' => $post['tags'],
                    'seo' => ['translations' => $post['translations']],
                    'is_featured' => $post['is_featured'],
                ]
            );
        }
    }

    protected function seedDraws(array $users, array $listings, array $orders): void
    {
        $platformDraw = DrawCampaign::updateOrCreate(
            ['slug' => 'cardora-april-drop'],
            [
                'campaign_type' => 'platform_volume',
                'title' => 'April Cardora Drop',
                'subtitle' => 'Κάθε 1€ τζίρου σε αγορές και πωλήσεις δίνει 1 συμμετοχή στο official drop του μήνα.',
                'description' => 'Όταν ο συνολικός τζίρος φτάσει τον στόχο, η πλατφόρμα θα κληρώσει το προκαθορισμένο συλλεκτικό αυτόματα.',
                'prize_title' => 'Charizard ex Special Illustration Rare PSA 10',
                'prize_category' => 'Κάρτες',
                'prize_condition' => 'PSA 10 Gem Mint',
                'prize_value' => 1000,
                'entries_per_euro' => 1,
                'target_amount' => 10000,
                'current_amount' => 3720,
                'entries_issued' => 3720,
                'participants_count' => 146,
                'status' => 'active',
                'featured' => true,
                'fairness_note' => 'Η κλήρωση εκτελείται από το σύστημα του Cardora μόλις πιαστεί ο στόχος.',
                'visual' => ['gradient' => 'from-[#29465d] via-[#182033] to-[#09111d]', 'label' => 'Cardora Drop'],
                'ends_at' => now()->addDays(21),
                'draw_at' => now()->addDays(22),
                'prize_listing_id' => $listings['charizard_psa10']->getKey(),
            ]
        );

        $communityDraw = DrawCampaign::updateOrCreate(
            ['slug' => 'one-piece-sealed-raffle'],
            [
                'host_user_id' => $users['andreas']->getKey(),
                'campaign_type' => 'community_raffle',
                'title' => 'One Piece sealed mini raffle',
                'subtitle' => '10€ x 40 θέσεις για sealed premium κομμάτι με Cardora-managed draw.',
                'description' => 'Ο διοργανωτής δηλώνει από πριν το αντικείμενο, το όριο θέσεων και το κόστος συμμετοχής.',
                'prize_title' => 'Pokémon 151 Booster Box Sealed',
                'prize_category' => 'Κάρτες',
                'prize_condition' => 'Factory Sealed',
                'prize_value' => 249,
                'entry_price' => 10,
                'target_amount' => 400,
                'current_amount' => 180,
                'target_entries' => 40,
                'entries_issued' => 18,
                'sold_entries' => 18,
                'participants_count' => 11,
                'max_entries_per_user' => 5,
                'status' => 'active',
                'featured' => true,
                'requires_verification' => true,
                'shipping_covered' => true,
                'fairness_note' => 'Η λίστα συμμετοχών κλειδώνει πριν την τελική draw εκτέλεση από το backend.',
                'visual' => ['gradient' => 'from-[#43597d] via-[#1c243b] to-[#09111d]', 'label' => 'Community Draw'],
                'ends_at' => now()->addDays(9),
                'draw_at' => now()->addDays(10),
            ]
        );

        DrawEntry::updateOrCreate(
            ['draw_campaign_id' => $platformDraw->getKey(), 'user_id' => $users['andreas']->getKey(), 'source_reference' => 'APRIL-ANDREAS'],
            ['order_id' => $orders['hot_toys']->getKey(), 'entries' => 120, 'amount' => 120, 'source_type' => 'platform_volume', 'status' => 'confirmed', 'entered_at' => now()->subDay()]
        );

        DrawEntry::updateOrCreate(
            ['draw_campaign_id' => $communityDraw->getKey(), 'user_id' => $users['andreas']->getKey(), 'source_reference' => 'COMMUNITY-ANDREAS'],
            ['entries' => 2, 'amount' => 20, 'source_type' => 'ticket_purchase', 'status' => 'confirmed', 'entered_at' => now()->subHours(12)]
        );
    }

    protected function seedCollectionsAndLikes(array $users, array $listings): void
    {
        CollectionEntry::updateOrCreate(
            ['user_id' => $users['andreas']->getKey(), 'product_id' => $listings['pokemon_loot_lot']->product_id],
            ['title' => 'Loot wall favorite', 'caption' => 'Pokémon lot με compact παρουσίαση hits.', 'media' => [['url' => '/brand-hero.png']], 'sort_order' => 1, 'is_featured' => true, 'visibility' => 'public']
        );

        CollectionEntry::updateOrCreate(
            ['user_id' => $users['nikos']->getKey(), 'product_id' => $listings['charizard_psa10']->product_id],
            ['title' => 'PSA vault', 'caption' => 'High-end slab με premium παρουσίαση.', 'media' => [['url' => '/brand-hero.png']], 'sort_order' => 1, 'is_featured' => true, 'visibility' => 'public']
        );

        CollectionEntry::updateOrCreate(
            ['user_id' => $users['eleni']->getKey(), 'product_id' => $listings['hot_toys_spiderman']->product_id],
            ['title' => 'Display piece', 'caption' => 'Boutique display για anime και premium figures.', 'media' => [['url' => '/brand-hero.png']], 'sort_order' => 1, 'is_featured' => true, 'visibility' => 'public']
        );

        ProfileLike::firstOrCreate(['user_id' => $users['nikos']->getKey(), 'profile_user_id' => $users['andreas']->getKey()]);
        ProfileLike::firstOrCreate(['user_id' => $users['maria']->getKey(), 'profile_user_id' => $users['andreas']->getKey()]);
        ProfileLike::firstOrCreate(['user_id' => $users['panos']->getKey(), 'profile_user_id' => $users['nikos']->getKey()]);
    }

    protected function seedVerification(array $users): void
    {
        $identity = VerificationSubmission::updateOrCreate(
            ['user_id' => $users['andreas']->getKey(), 'verification_type' => 'identity'],
            ['status' => 'approved', 'payload' => ['legal_name' => 'Ανδρέας Μανιάτης'], 'reviewer_notes' => 'Η ταυτότητα επιβεβαιώθηκε χωρίς παρατηρήσεις.', 'submitted_at' => now()->subDays(15), 'reviewed_at' => now()->subDays(14)]
        );

        $identity->documents()->updateOrCreate(
            ['document_type' => 'Αστυνομική ταυτότητα'],
            ['storage_disk' => 'public', 'storage_path' => 'uploads/verification/id-front.png', 'original_name' => 'identity-front.png', 'mime_type' => 'image/png', 'file_size' => 204800, 'uploaded_at' => now()->subDays(15)]
        );

        $address = VerificationSubmission::updateOrCreate(
            ['user_id' => $users['andreas']->getKey(), 'verification_type' => 'address'],
            ['status' => 'submitted', 'payload' => ['city' => 'Αθήνα'], 'reviewer_notes' => null, 'submitted_at' => now()->subDays(2)]
        );

        $address->documents()->updateOrCreate(
            ['document_type' => 'Λογαριασμός κοινής ωφέλειας'],
            ['storage_disk' => 'public', 'storage_path' => 'uploads/verification/address-proof.pdf', 'original_name' => 'utility-bill.pdf', 'mime_type' => 'application/pdf', 'file_size' => 512000, 'uploaded_at' => now()->subDays(2)]
        );
    }

    protected function seedSupportAndNotifications(array $users, array $orders): void
    {
        SupportTicket::updateOrCreate(
            ['user_id' => $users['andreas']->getKey(), 'subject' => 'Ερώτηση για release escrow σε completed order'],
            ['order_id' => $orders['hot_toys']->getKey(), 'category' => 'Πληρωμές', 'status' => 'open', 'priority' => 'normal', 'description' => 'Θέλω να επιβεβαιώσω πότε φαίνεται το release στον seller dashboard.']
        );

        Notification::updateOrCreate(
            ['user_id' => $users['andreas']->getKey(), 'type' => 'verification_submitted', 'title' => 'Νέα υποβολή verification'],
            ['body' => 'Η νέα ενότητα διεύθυνσης στάλθηκε για έλεγχο.', 'data' => ['verification_type' => 'address']]
        );

        Notification::updateOrCreate(
            ['user_id' => $users['andreas']->getKey(), 'type' => 'message_received', 'title' => 'Νέο μήνυμα από seller'],
            ['body' => 'Ο NikosSlabs απάντησε στη συνομιλία για το Lugia V Alt Art PSA 10.', 'data' => ['listing_slug' => 'lugia-v-alt-psa10']]
        );
    }

    protected function seedReviews(array $users, array $listings, array $orders): void
    {
        Review::updateOrCreate(
            ['order_id' => $orders['hot_toys']->getKey(), 'reviewer_id' => $users['andreas']->getKey(), 'reviewee_id' => $users['eleni']->getKey()],
            ['listing_id' => $listings['hot_toys_spiderman']->getKey(), 'product_id' => $listings['hot_toys_spiderman']->product_id, 'rating' => 5, 'title' => 'Άριστη παρουσίαση και συσκευασία', 'body' => 'Η φιγούρα έφτασε ακριβώς όπως περιγραφόταν, με πολύ σωστή προστασία στο κουτί.', 'is_public' => true]
        );
    }

    protected function userData(): array
    {
        return [
            'andreas' => [
                'name' => 'Ανδρέας Μανιάτης',
                'display_name' => 'AndreasVault',
                'handle' => 'andreas-vault',
                'email' => 'andreas@cardora.local',
                'phone' => '6944001122',
                'city' => 'Αθήνα',
                'bio' => 'Pokémon slabs, Marvel grails και premium sealed shelves.',
                'collector_tagline' => 'Pokémon slabs, Marvel grails και premium sealed shelves.',
                'favorite_categories' => ['cards', 'comics'],
                'trust_status' => 'basic',
                'rating' => 4.90,
                'sales_count' => 12,
                'purchase_count' => 18,
                'is_verified_seller' => false,
                'profile_metadata' => [
                    'el' => ['intro' => 'Στήνω τη συλλογή μου σαν curated wall με graded κάρτες και key comics.', 'badges' => ['PSA Slabs', 'Marvel Keys'], 'collection_moments' => ['Αναβαθμίζω το Pokémon shelf μόνο με clean slabs.', 'Κρατάω ξεχωριστό comic section για key issues.']],
                    'en' => ['intro' => 'I build my collection like a curated wall with graded cards and key comics.', 'badges' => ['PSA Slabs', 'Marvel Keys'], 'collection_moments' => ['I upgrade the Pokémon shelf only with clean slabs.', 'I keep a dedicated comic section for key issues.']],
                ],
            ],
            'nikos' => [
                'name' => 'Νίκος Μακρής',
                'display_name' => 'NikosSlabs',
                'handle' => 'nikos-slabs',
                'email' => 'nikos@cardora.local',
                'phone' => '6944001188',
                'city' => 'Θεσσαλονίκη',
                'bio' => 'High-end Pokémon investment cards, sealed boxes και auction grails.',
                'collector_tagline' => 'High-end Pokémon investment cards, sealed boxes και auction grails.',
                'favorite_categories' => ['cards'],
                'trust_status' => 'elite',
                'rating' => 4.97,
                'sales_count' => 214,
                'purchase_count' => 41,
                'is_verified_seller' => true,
                'profile_metadata' => [
                    'el' => ['intro' => 'Το προφίλ μου είναι χτισμένο γύρω από graded Pokémon, reserve auctions και clean sealed προϊόντα.', 'badges' => ['Auction Host', 'PSA Specialist', 'Sealed Vault']],
                    'en' => ['intro' => 'My profile is built around graded Pokémon, reserve auctions and clean sealed product.', 'badges' => ['Auction Host', 'PSA Specialist', 'Sealed Vault']],
                ],
            ],
            'eleni' => [
                'name' => 'Ελένη Στεργίου',
                'display_name' => 'EleniFigures',
                'handle' => 'eleni-figures',
                'email' => 'eleni@cardora.local',
                'phone' => '6944002211',
                'city' => 'Πάτρα',
                'bio' => 'Anime statues, One Piece figures και display pieces με clean presentation.',
                'collector_tagline' => 'Anime statues, One Piece figures και display pieces με clean presentation.',
                'favorite_categories' => ['figures'],
                'trust_status' => 'elite',
                'rating' => 4.94,
                'sales_count' => 87,
                'purchase_count' => 26,
                'is_verified_seller' => true,
                'profile_metadata' => [
                    'el' => ['intro' => 'Μου αρέσουν οι φιγούρες που δείχνουν σαν gallery piece, με έμφαση στο sculpt και το box condition.', 'badges' => ['Anime Figures', 'Display Curator']],
                    'en' => ['intro' => 'I like figures that look like gallery pieces, with focus on sculpt and box condition.', 'badges' => ['Anime Figures', 'Display Curator']],
                ],
            ],
            'maria' => [
                'name' => 'Μαρία Λάμπρου',
                'display_name' => 'MariaKeys',
                'handle' => 'maria-keys',
                'email' => 'maria@cardora.local',
                'phone' => '6944003344',
                'city' => 'Ηράκλειο',
                'bio' => 'Marvel/DC keys, deluxe books και signed collector editions.',
                'collector_tagline' => 'Marvel/DC keys, deluxe books και signed collector editions.',
                'favorite_categories' => ['comics'],
                'trust_status' => 'basic',
                'rating' => 4.83,
                'sales_count' => 39,
                'purchase_count' => 21,
                'is_verified_seller' => false,
                'profile_metadata' => ['el' => ['intro' => 'Η συλλογή μου εστιάζει σε key issues, deluxe hardcovers και premium εκδόσεις.'], 'en' => ['intro' => 'My collection focuses on key issues, deluxe hardcovers and premium editions.']],
            ],
            'panos' => [
                'name' => 'Πάνος Κυριαζής',
                'display_name' => 'PanosPops',
                'handle' => 'panos-pops',
                'email' => 'panos@cardora.local',
                'phone' => '6944004455',
                'city' => 'Λάρισα',
                'bio' => 'Funko chase pieces, convention exclusives και premium pop walls.',
                'collector_tagline' => 'Funko chase pieces, convention exclusives και premium pop walls.',
                'favorite_categories' => ['figures', 'misc'],
                'trust_status' => 'basic',
                'rating' => 4.71,
                'sales_count' => 16,
                'purchase_count' => 32,
                'is_verified_seller' => false,
                'profile_metadata' => ['el' => ['intro' => 'Συλλέγω κυρίως Funko και convention items με έμφαση σε exclusives και clean boxes.'], 'en' => ['intro' => 'I mostly collect Funko and convention items with focus on exclusives and clean boxes.']],
            ],
        ];
    }

    protected function listingData(): array
    {
        return [
            'charizard_psa10' => [
                'category_key' => 'cards',
                'seller_key' => 'nikos',
                'slug' => 'charizard-ex-special-illustration-rare-psa-10',
                'sku' => 'CDR-CARD-0001',
                'title' => 'Charizard ex Special Illustration Rare PSA 10',
                'subtitle' => 'Scarlet & Violet 151 premium slab',
                'franchise' => 'Pokemon',
                'series' => 'Scarlet & Violet',
                'brand' => 'PSA',
                'year' => 2023,
                'language' => 'English',
                'set_name' => '151',
                'item_number' => '199/165',
                'product_type' => 'Graded Cards',
                'price' => 1295,
                'old_price' => 1380,
                'minimum_offer' => 1180,
                'quantity' => 1,
                'available_quantity' => 1,
                'condition' => 'Mint',
                'rarity' => 'Alt Art',
                'status' => 'active',
                'sale_format' => 'fixed_price',
                'shipping_cost' => 7.5,
                'dispatch_time' => '1-2 days',
                'availability' => 'in_stock',
                'accept_offers' => true,
                'is_featured' => true,
                'published_days_ago' => 5,
                'tags' => ['Pokemon', 'PSA 10', '151', 'Charizard'],
                'specifications' => ['grade' => 'PSA 10 Gem Mint', 'surface' => 'Clean', 'centering' => 'Premium'],
                'attributes' => [
                    'franchise' => 'Pokemon',
                    'series' => 'Scarlet & Violet',
                    'brand' => 'PSA',
                    'condition' => 'Mint',
                    'rarity' => 'Alt Art',
                    'type_label' => 'Graded Cards',
                    'graded_company' => 'PSA',
                    'grade' => '10 Gem Mint',
                    'set_name' => '151',
                    'card_number' => '199/165',
                    'language' => 'English',
                    'authenticity' => 'PSA cert verified',
                    'returns_policy' => 'Returns are accepted only if the slab materially differs from the listing photos.',
                ],
                'media' => [
                    ['path' => 'seed/cards/charizard-front.jpg', 'url' => '/storage/seed/cards/charizard-front.jpg', 'kind' => 'image', 'label' => 'Front'],
                    ['path' => 'seed/cards/charizard-back.jpg', 'url' => '/storage/seed/cards/charizard-back.jpg', 'kind' => 'image', 'label' => 'Back'],
                ],
                'translations' => [
                    'el' => [
                        'description' => 'Καθαρό premium PSA 10 slab για σοβαρούς Pokemon συλλέκτες, με δυνατή παρουσίαση και ασφαλή αποστολή.',
                        'short_description' => 'Premium slab με καθαρό cert και δυνατό centering.',
                        'shipping_info' => 'Αποστολή σε protective sleeve, bubble wrap και σκληρό box.',
                        'authenticity' => 'Επιβεβαιωμένο PSA cert και matching slab details.',
                        'highlights' => ['PSA 10 Gem Mint', 'Clean cert', 'Strong eye appeal'],
                    ],
                    'en' => [
                        'description' => 'Premium PSA 10 slab for serious Pokemon collectors, presented cleanly and shipped with full protection.',
                        'short_description' => 'Premium slab with clean cert and strong centering.',
                        'shipping_info' => 'Shipped in a protective sleeve, bubble wrap and rigid box.',
                        'authenticity' => 'Verified PSA cert with matching slab details.',
                        'highlights' => ['PSA 10 Gem Mint', 'Clean cert', 'Strong eye appeal'],
                    ],
                ],
                'visual' => ['gradient' => 'from-[#204178] via-[#14223b] to-[#09111d]', 'label' => 'PSA Slab', 'finish' => 'Collector Grade'],
            ],
            'lugia_auction' => [
                'category_key' => 'cards',
                'seller_key' => 'nikos',
                'slug' => 'lugia-v-alt-psa10',
                'sku' => 'CDR-CARD-0002',
                'title' => 'Lugia V Alt Art PSA 10',
                'subtitle' => 'Auction listing with reserve',
                'franchise' => 'Pokemon',
                'series' => 'Sword & Shield',
                'brand' => 'PSA',
                'year' => 2022,
                'language' => 'English',
                'set_name' => 'Silver Tempest',
                'item_number' => '186/195',
                'product_type' => 'Graded Cards',
                'price' => 760,
                'quantity' => 1,
                'available_quantity' => 1,
                'condition' => 'Mint',
                'rarity' => 'Alt Art',
                'status' => 'active',
                'sale_format' => 'auction',
                'shipping_cost' => 8,
                'dispatch_time' => '1-2 days',
                'availability' => 'auction_live',
                'accept_offers' => false,
                'published_days_ago' => 3,
                'starting_bid' => 760,
                'current_bid' => 760,
                'reserve_price' => 700,
                'bid_increment' => 20,
                'buyout_price' => 980,
                'auction_starts_at' => now()->subDays(1),
                'auction_ends_at' => now()->addDays(5),
                'tags' => ['Pokemon', 'Lugia', 'Auction', 'PSA 10'],
                'specifications' => ['grade' => 'PSA 10 Gem Mint', 'auction_type' => 'Reserve'],
                'attributes' => [
                    'franchise' => 'Pokemon',
                    'series' => 'Silver Tempest',
                    'brand' => 'PSA',
                    'condition' => 'Mint',
                    'rarity' => 'Alt Art',
                    'type_label' => 'Graded Cards',
                    'graded_company' => 'PSA',
                    'grade' => '10 Gem Mint',
                    'set_name' => 'Silver Tempest',
                    'card_number' => '186/195',
                    'language' => 'English',
                    'authenticity' => 'PSA cert verified',
                    'returns_policy' => 'Auction lots are reviewed only when the delivered slab materially differs from the listing.',
                ],
                'media' => [
                    ['path' => 'seed/cards/lugia-front.jpg', 'url' => '/storage/seed/cards/lugia-front.jpg', 'kind' => 'image', 'label' => 'Front'],
                    ['path' => 'seed/cards/lugia-back.jpg', 'url' => '/storage/seed/cards/lugia-back.jpg', 'kind' => 'image', 'label' => 'Back'],
                ],
                'translations' => [
                    'el' => [
                        'description' => 'High-end δημοπρασία για premium Lugia slab με ενεργό bidding ενδιαφέρον και reserve price.',
                        'short_description' => 'Auction format με reserve και buyout.',
                        'shipping_info' => 'Συσκευασία σε rigid slab shield και ασφαλισμένη αποστολή.',
                        'authenticity' => 'Το slab επαληθεύεται μέσω PSA cert πριν την αποστολή.',
                        'highlights' => ['Auction listing', 'Reserve met flow', 'PSA 10 slab'],
                    ],
                    'en' => [
                        'description' => 'High-end auction listing for a premium Lugia slab with active bidding interest and reserve price.',
                        'short_description' => 'Auction format with reserve and buyout.',
                        'shipping_info' => 'Packed in a rigid slab shield with insured shipping.',
                        'authenticity' => 'The slab is checked against the PSA cert before dispatch.',
                        'highlights' => ['Auction listing', 'Reserve met flow', 'PSA 10 slab'],
                    ],
                ],
                'visual' => ['gradient' => 'from-[#4368a6] via-[#1c2f56] to-[#0d1626]', 'label' => 'Auction', 'finish' => 'Alt Art'],
            ],
            'pokemon_151_box' => [
                'category_key' => 'cards',
                'seller_key' => 'nikos',
                'slug' => 'pokemon-151-booster-box',
                'sku' => 'CDR-CARD-0003',
                'title' => 'Pokemon 151 Sealed Booster Bundle',
                'subtitle' => 'Factory sealed collector product',
                'franchise' => 'Pokemon',
                'series' => 'Scarlet & Violet',
                'brand' => 'The Pokemon Company',
                'year' => 2023,
                'language' => 'English',
                'set_name' => '151',
                'item_number' => 'SEALED-151',
                'product_type' => 'Booster Boxes',
                'price' => 189,
                'quantity' => 3,
                'available_quantity' => 3,
                'condition' => 'Sealed',
                'rarity' => 'Premium',
                'status' => 'active',
                'sale_format' => 'fixed_price',
                'shipping_cost' => 6,
                'dispatch_time' => '1-2 days',
                'availability' => 'in_stock',
                'accept_offers' => true,
                'published_days_ago' => 8,
                'tags' => ['Pokemon', '151', 'Sealed', 'Booster'],
                'specifications' => ['seal' => 'Factory sealed', 'storage' => 'Temperature controlled'],
                'attributes' => [
                    'franchise' => 'Pokemon',
                    'series' => 'Scarlet & Violet',
                    'brand' => 'The Pokemon Company',
                    'condition' => 'Sealed',
                    'rarity' => 'Premium',
                    'type_label' => 'Booster Boxes',
                    'graded_company' => 'Factory Sealed',
                    'grade' => 'Factory Sealed',
                    'set_name' => '151',
                    'language' => 'English',
                    'returns_policy' => 'Returns are available only if the seal arrives materially damaged.',
                ],
                'media' => [
                    ['path' => 'seed/cards/151-box.jpg', 'url' => '/storage/seed/cards/151-box.jpg', 'kind' => 'image', 'label' => 'Sealed box'],
                ],
                'translations' => [
                    'el' => [
                        'description' => 'Σφραγισμένο product για collectors που θέλουν clean sealed παρουσίαση και ασφαλή παράδοση.',
                        'short_description' => 'Factory sealed 151 bundle με προσεκτική φύλαξη.',
                        'shipping_info' => 'Διπλό κουτί, bubble προστασία και seal photos πριν την αποστολή.',
                        'authenticity' => 'Καταγράφονται corners, seals και outer wrap πριν φύγει.',
                        'highlights' => ['Factory sealed', 'Stored clean', 'Collector-ready'],
                    ],
                    'en' => [
                        'description' => 'Sealed product for collectors who want a clean sealed presentation and protected delivery.',
                        'short_description' => 'Factory sealed 151 bundle stored carefully.',
                        'shipping_info' => 'Double-boxed with bubble protection and seal photos before dispatch.',
                        'authenticity' => 'Corners, seals and outer wrap are documented before shipping.',
                        'highlights' => ['Factory sealed', 'Stored clean', 'Collector-ready'],
                    ],
                ],
                'visual' => ['gradient' => 'from-[#1c4b70] via-[#15253b] to-[#0a1019]', 'label' => 'Sealed', 'finish' => 'Collector Box'],
            ],
            'pokemon_loot_lot' => [
                'category_key' => 'cards',
                'seller_key' => 'andreas',
                'slug' => 'pokemon-mid-era-loot-lot',
                'sku' => 'CDR-CARD-0004',
                'title' => 'Pokemon Mid-Era Loot Lot 42 Cards',
                'subtitle' => 'Compact lot with named hits and clear themes',
                'franchise' => 'Pokemon',
                'series' => 'Mixed eras',
                'brand' => 'Mixed',
                'year' => 2024,
                'language' => 'Japanese / English',
                'set_name' => 'Mixed',
                'item_number' => 'LOT-42',
                'product_type' => 'Sets',
                'price' => 96,
                'quantity' => 1,
                'available_quantity' => 1,
                'condition' => 'Near Mint',
                'rarity' => 'Collector Lot',
                'status' => 'active',
                'sale_format' => 'fixed_price',
                'shipping_cost' => 5,
                'dispatch_time' => '1-2 days',
                'availability' => 'in_stock',
                'accept_offers' => true,
                'published_days_ago' => 2,
                'is_lot' => true,
                'lot_configuration' => [
                    'total_cards' => 42,
                    'guaranteed_hits' => 5,
                    'preview_cards' => ['Mew ex', 'Pikachu AR', 'Gengar holo', 'Umbreon V', 'Rayquaza V'],
                    'themes' => ['Alt art vibes', 'Vintage holo energy', 'Starter favorites'],
                    'summary' => 'Curated lot with visible hits, cleaner commons and no bulk dump presentation.',
                    'condition_mix' => 'Mostly Near Mint with a few Excellent binder pieces.',
                ],
                'tags' => ['Pokemon', 'Loot Lot', 'Binder Lot'],
                'specifications' => ['sorting' => 'Sleeved hits and grouped themes'],
                'attributes' => [
                    'franchise' => 'Pokemon',
                    'series' => 'Mixed eras',
                    'brand' => 'Mixed',
                    'condition' => 'Near Mint',
                    'rarity' => 'Collector Lot',
                    'type_label' => 'Loot Lot',
                    'language' => 'Japanese / English',
                    'returns_policy' => 'The lot is protected if the named hits do not match the delivered contents.',
                    'lot_summary' => [
                        'total_cards' => 42,
                        'guaranteed_hits' => 5,
                        'preview_cards' => ['Mew ex', 'Pikachu AR', 'Gengar holo', 'Umbreon V', 'Rayquaza V'],
                        'themes' => ['Alt art vibes', 'Vintage holo energy', 'Starter favorites'],
                    ],
                ],
                'media' => [
                    ['path' => 'seed/cards/loot-lot-main.jpg', 'url' => '/storage/seed/cards/loot-lot-main.jpg', 'kind' => 'image', 'label' => 'Lot overview'],
                ],
                'translations' => [
                    'el' => [
                        'description' => 'Loot lot με compact παρουσίαση hits, θεμάτων και condition mix ώστε να ξέρει ο αγοραστής τι παίρνει χωρίς μακρινάρι.',
                        'short_description' => '42 κάρτες με 5 named hits και καθαρό summary.',
                        'shipping_info' => 'Τα hits μπαίνουν σε sleeves/toploaders και τα υπόλοιπα σε team bags με rigid support.',
                        'authenticity' => 'Τα named hits φωτογραφίζονται και σημειώνονται στο packing slip.',
                        'highlights' => ['42 cards', '5 named hits', 'Curated lot'],
                    ],
                    'en' => [
                        'description' => 'Loot lot with a compact presentation of hits, themes and condition mix so the buyer knows what is included without a wall of text.',
                        'short_description' => '42 cards with 5 named hits and a clean summary.',
                        'shipping_info' => 'Hits are sleeved/toploaded and the rest are grouped in team bags with rigid support.',
                        'authenticity' => 'Named hits are photographed and listed in the packing slip.',
                        'highlights' => ['42 cards', '5 named hits', 'Curated lot'],
                    ],
                ],
                'visual' => ['gradient' => 'from-[#26557d] via-[#1b2741] to-[#0a111c]', 'label' => 'Loot Lot', 'finish' => 'Bundle Flow'],
            ],
            'hot_toys_spiderman' => [
                'category_key' => 'figures',
                'seller_key' => 'eleni',
                'slug' => 'hot-toys-spider-man-advanced-suit',
                'sku' => 'CDR-FIG-0001',
                'title' => 'Hot Toys Spider-Man Advanced Suit',
                'subtitle' => 'Deluxe figure with clean box condition',
                'franchise' => 'Marvel',
                'series' => 'Spider-Man',
                'brand' => 'Hot Toys',
                'year' => 2022,
                'language' => null,
                'set_name' => 'Marvel Collection',
                'item_number' => 'HT-SPIDEY-01',
                'product_type' => 'Action Figure',
                'price' => 415,
                'quantity' => 1,
                'available_quantity' => 1,
                'condition' => 'Excellent',
                'rarity' => 'Deluxe',
                'status' => 'active',
                'sale_format' => 'fixed_price',
                'shipping_cost' => 12,
                'dispatch_time' => '1-2 days',
                'availability' => 'in_stock',
                'accept_offers' => true,
                'published_days_ago' => 7,
                'tags' => ['Marvel', 'Hot Toys', 'Spider-Man'],
                'specifications' => ['box_condition' => 'Excellent', 'complete' => true],
                'attributes' => [
                    'franchise' => 'Marvel',
                    'series' => 'Spider-Man',
                    'brand' => 'Hot Toys',
                    'condition' => 'Excellent',
                    'rarity' => 'Deluxe',
                    'type_label' => 'Action Figure',
                    'returns_policy' => 'Returns are accepted only if the figure or accessories materially differ from the listing.',
                ],
                'media' => [
                    ['path' => 'seed/figures/hot-toys-spiderman.jpg', 'url' => '/storage/seed/figures/hot-toys-spiderman.jpg', 'kind' => 'image', 'label' => 'Display view'],
                ],
                'translations' => [
                    'el' => [
                        'description' => 'Σοβαρή φιγούρα display με καθαρό box condition, πλήρη αξεσουάρ και premium προστασία στην αποστολή.',
                        'short_description' => 'Hot Toys figure με πολύ σωστό sculpt και ολοκληρωμένο set.',
                        'shipping_info' => 'Αποστολή με outer box, corner protection και έξτρα padding στα αξεσουάρ.',
                        'authenticity' => 'Σειριακός έλεγχος και φωτογράφιση contents πριν το κλείσιμο του κουτιού.',
                        'highlights' => ['Complete accessories', 'Display-ready', 'Protected packing'],
                    ],
                    'en' => [
                        'description' => 'Serious display figure with clean box condition, full accessories and premium shipping protection.',
                        'short_description' => 'Hot Toys figure with strong sculpt and complete accessory set.',
                        'shipping_info' => 'Shipped with an outer box, corner protection and extra padding for the accessories.',
                        'authenticity' => 'Serial and contents are checked before the package is sealed.',
                        'highlights' => ['Complete accessories', 'Display-ready', 'Protected packing'],
                    ],
                ],
                'visual' => ['gradient' => 'from-[#5e2037] via-[#182035] to-[#0a111d]', 'label' => 'Hot Toys', 'finish' => 'Display Piece'],
            ],
            'berserk_deluxe' => [
                'category_key' => 'comics',
                'seller_key' => 'maria',
                'slug' => 'berserk-deluxe-volume-1-sealed',
                'sku' => 'CDR-COM-0001',
                'title' => 'Berserk Deluxe Volume 1 Sealed',
                'subtitle' => 'Dark Horse hardcover collector edition',
                'franchise' => 'Berserk',
                'series' => 'Deluxe Edition',
                'brand' => 'Dark Horse',
                'year' => 2019,
                'language' => 'English',
                'set_name' => 'Deluxe Volume 1',
                'item_number' => 'BERSERK-DLX-1',
                'product_type' => 'Deluxe Edition',
                'price' => 44,
                'quantity' => 2,
                'available_quantity' => 2,
                'condition' => 'Sealed',
                'rarity' => 'Deluxe',
                'status' => 'active',
                'sale_format' => 'fixed_price',
                'shipping_cost' => 5,
                'dispatch_time' => '1-2 days',
                'availability' => 'in_stock',
                'accept_offers' => true,
                'published_days_ago' => 12,
                'tags' => ['Berserk', 'Deluxe', 'Hardcover'],
                'specifications' => ['edition' => 'Hardcover', 'seal' => 'Publisher shrink'],
                'attributes' => [
                    'franchise' => 'Berserk',
                    'series' => 'Deluxe Edition',
                    'brand' => 'Dark Horse',
                    'condition' => 'Sealed',
                    'rarity' => 'Deluxe',
                    'type_label' => 'Deluxe Edition',
                    'returns_policy' => 'Protected if the delivered copy arrives unsealed or materially damaged.',
                ],
                'media' => [
                    ['path' => 'seed/comics/berserk-deluxe.jpg', 'url' => '/storage/seed/comics/berserk-deluxe.jpg', 'kind' => 'image', 'label' => 'Cover'],
                ],
                'translations' => [
                    'el' => [
                        'description' => 'Collector hardcover έκδοση με καθαρό shrink και πολύ προσεγμένη συσκευασία για να μη χτυπηθούν corners.',
                        'short_description' => 'Sealed deluxe hardcover για serious manga shelves.',
                        'shipping_info' => 'Book wrap, corner pads και rigid mailer μέσα σε δεύτερο κουτί.',
                        'authenticity' => 'Φωτογραφίζεται η εξωτερική κατάσταση και το shrink πριν την αποστολή.',
                        'highlights' => ['Sealed hardcover', 'Corner-safe packing', 'Collector condition'],
                    ],
                    'en' => [
                        'description' => 'Collector hardcover edition with clean shrink and careful packaging to keep the corners safe.',
                        'short_description' => 'Sealed deluxe hardcover for serious manga shelves.',
                        'shipping_info' => 'Book wrap, corner pads and a rigid mailer inside a second box.',
                        'authenticity' => 'The outer condition and shrink are documented before shipping.',
                        'highlights' => ['Sealed hardcover', 'Corner-safe packing', 'Collector condition'],
                    ],
                ],
                'visual' => ['gradient' => 'from-[#3b2a69] via-[#151c33] to-[#0a101b]', 'label' => 'Deluxe', 'finish' => 'Collector Shelf'],
            ],
            'batman_adventures' => [
                'category_key' => 'comics',
                'seller_key' => 'maria',
                'slug' => 'batman-adventures-12-raw',
                'sku' => 'CDR-COM-0002',
                'title' => 'Batman Adventures #12 Raw Copy',
                'subtitle' => 'Key Harley Quinn issue',
                'franchise' => 'DC',
                'series' => 'Batman Adventures',
                'brand' => 'DC Comics',
                'year' => 1993,
                'language' => 'English',
                'set_name' => 'Batman Adventures',
                'item_number' => '#12',
                'product_type' => 'Comic',
                'price' => 675,
                'quantity' => 1,
                'available_quantity' => 1,
                'condition' => 'Very Good',
                'rarity' => 'Key Issue',
                'status' => 'active',
                'sale_format' => 'fixed_price',
                'shipping_cost' => 9,
                'dispatch_time' => '1-2 days',
                'availability' => 'in_stock',
                'accept_offers' => true,
                'published_days_ago' => 10,
                'tags' => ['DC', 'Batman', 'Harley Quinn', 'Key Issue'],
                'specifications' => ['bagged_boarded' => true, 'notes' => 'Press candidate'],
                'attributes' => [
                    'franchise' => 'DC',
                    'series' => 'Batman Adventures',
                    'brand' => 'DC Comics',
                    'condition' => 'Very Good',
                    'rarity' => 'Key Issue',
                    'type_label' => 'Comic',
                    'returns_policy' => 'Protected only for major condition mismatch versus the documented photos.',
                ],
                'media' => [
                    ['path' => 'seed/comics/batman-12.jpg', 'url' => '/storage/seed/comics/batman-12.jpg', 'kind' => 'image', 'label' => 'Front cover'],
                ],
                'translations' => [
                    'el' => [
                        'description' => 'Key issue σε raw μορφή με καθαρή φωτογράφιση front/back και προσεκτικό shipping για να διατηρηθεί η κατάσταση.',
                        'short_description' => 'Raw copy key issue με bag, board και documented flaws.',
                        'shipping_info' => 'Toploader comic shield, rigid support και ασφαλισμένη αποστολή.',
                        'authenticity' => 'Φωτογραφημένες γωνίες, spine και πίσω όψη πριν το dispatch.',
                        'highlights' => ['Key issue', 'Documented flaws', 'Rigid comic packing'],
                    ],
                    'en' => [
                        'description' => 'Raw key issue with clean front/back photography and careful shipping to preserve condition.',
                        'short_description' => 'Raw key issue with bag, board and documented flaws.',
                        'shipping_info' => 'Comic toploader, rigid support and insured shipping.',
                        'authenticity' => 'Corners, spine and back cover are documented before dispatch.',
                        'highlights' => ['Key issue', 'Documented flaws', 'Rigid comic packing'],
                    ],
                ],
                'visual' => ['gradient' => 'from-[#48347a] via-[#1a2238] to-[#0a101b]', 'label' => 'Key Issue', 'finish' => 'Comic Vault'],
            ],
            'funko_mandalorian' => [
                'category_key' => 'misc',
                'seller_key' => 'panos',
                'slug' => 'funko-pop-mandalorian-chase',
                'sku' => 'CDR-MISC-0001',
                'title' => 'Funko Pop Mandalorian Chase',
                'subtitle' => 'Protected chase piece with clean window',
                'franchise' => 'Star Wars',
                'series' => 'The Mandalorian',
                'brand' => 'Funko',
                'year' => 2021,
                'language' => null,
                'set_name' => 'Star Wars',
                'item_number' => 'CHASE-001',
                'product_type' => 'Memorabilia',
                'price' => 82,
                'quantity' => 1,
                'available_quantity' => 1,
                'condition' => 'Near Mint',
                'rarity' => 'Chase',
                'status' => 'active',
                'sale_format' => 'fixed_price',
                'shipping_cost' => 6,
                'dispatch_time' => '1-2 days',
                'availability' => 'in_stock',
                'accept_offers' => true,
                'published_days_ago' => 4,
                'tags' => ['Funko', 'Chase', 'Star Wars'],
                'specifications' => ['protector' => 'Hard stack included'],
                'attributes' => [
                    'franchise' => 'Star Wars',
                    'series' => 'The Mandalorian',
                    'brand' => 'Funko',
                    'condition' => 'Near Mint',
                    'rarity' => 'Chase',
                    'type_label' => 'Memorabilia',
                    'returns_policy' => 'Protected if the delivered box differs from the documented condition.',
                ],
                'media' => [
                    ['path' => 'seed/misc/funko-mando.jpg', 'url' => '/storage/seed/misc/funko-mando.jpg', 'kind' => 'image', 'label' => 'Front'],
                ],
                'translations' => [
                    'el' => [
                        'description' => 'Chase κομμάτι με hard stack protector και καθαρή περιγραφή παραθύρου, corners και top panel.',
                        'short_description' => 'Funko chase με documented box condition.',
                        'shipping_info' => 'Πάντα με protector, filler material και εξωτερικό κουτί χωρίς πίεση.',
                        'authenticity' => 'Σημειώνεται η chase έκδοση και φωτογραφίζεται ο κωδικός κουτιού.',
                        'highlights' => ['Chase edition', 'Hard stack included', 'Documented box condition'],
                    ],
                    'en' => [
                        'description' => 'Chase piece with a hard stack protector and clear documentation of the window, corners and top panel.',
                        'short_description' => 'Funko chase with documented box condition.',
                        'shipping_info' => 'Always shipped with a protector, filler material and a pressure-free outer box.',
                        'authenticity' => 'The chase edition and box code are documented before shipping.',
                        'highlights' => ['Chase edition', 'Hard stack included', 'Documented box condition'],
                    ],
                ],
                'visual' => ['gradient' => 'from-[#25514f] via-[#112033] to-[#0a111d]', 'label' => 'Chase', 'finish' => 'Pop Vault'],
            ],
        ];
    }

    protected function blogData(): array
    {
        return [
            [
                'category_key' => 'trust',
                'author_key' => 'nikos',
                'slug' => 'how-cardora-protected-payments-work',
                'title' => 'How Cardora Protected Payments Work',
                'excerpt' => 'A simple guide to moderation, protected checkout, buyer confirmation and payout release.',
                'published_days_ago' => 15,
                'is_featured' => true,
                'tags' => ['Escrow', 'Trust', 'Marketplace'],
                'cover_media' => ['url' => '/storage/seed/blog/protected-payments.jpg', 'kind' => 'image'],
                'content' => [
                    'translations' => [
                        'el' => [
                            'intro' => 'Στο Cardora η πληρωμή δεν φεύγει αμέσως στον πωλητή. Κρατιέται προστατευμένα μέχρι να ολοκληρωθεί σωστά η παραλαβή.',
                            'sections' => [
                                ['title' => 'Έλεγχος πριν το public listing', 'text' => 'Κάθε αγγελία περνά πρώτα από moderation ώστε οι πληροφορίες και οι φωτογραφίες να είναι επαρκείς.'],
                                ['title' => 'Protected checkout', 'text' => 'Ο αγοραστής ολοκληρώνει την παραγγελία μέσα από το Cardora και το ποσό δεσμεύεται με ασφάλεια.'],
                                ['title' => 'Release μετά την επιβεβαίωση', 'text' => 'Η αποδέσμευση γίνεται μόνο όταν η παραγγελία φτάσει σωστά ή λήξει καθαρά το review window.'],
                            ],
                        ],
                        'en' => [
                            'intro' => 'On Cardora, the money does not go straight to the seller. It stays protected until the delivery is completed correctly.',
                            'sections' => [
                                ['title' => 'Review before a listing goes public', 'text' => 'Each listing goes through moderation first so the information and photo set are strong enough.'],
                                ['title' => 'Protected checkout', 'text' => 'The buyer completes the order through Cardora and the funds stay protected.'],
                                ['title' => 'Release after confirmation', 'text' => 'Payout is released only when the order is confirmed or the review window closes cleanly.'],
                            ],
                        ],
                    ],
                ],
                'translations' => [
                    'el' => ['title' => 'Πώς λειτουργεί το Cardora Protected Payment', 'excerpt' => 'Ένας απλός οδηγός για moderation, protected checkout, επιβεβαίωση παραλαβής και release.'],
                    'en' => ['title' => 'How Cardora Protected Payments Work', 'excerpt' => 'A simple guide to moderation, protected checkout, buyer confirmation and payout release.'],
                ],
            ],
            [
                'category_key' => 'cards',
                'author_key' => 'andreas',
                'slug' => 'what-makes-a-card-listing-look-premium',
                'title' => 'What Makes a Card Listing Look Premium',
                'excerpt' => 'The small details that make graded cards, sealed boxes and raw singles look credible and collectible.',
                'published_days_ago' => 9,
                'is_featured' => true,
                'tags' => ['Cards', 'Listings', 'Seller Tips'],
                'cover_media' => ['url' => '/storage/seed/blog/card-listing-guide.jpg', 'kind' => 'image'],
                'content' => [
                    'translations' => [
                        'el' => [
                            'intro' => 'Οι σοβαρές αγγελίες καρτών δεν είναι απλώς θέμα τιμής. Είναι φωτογραφίες, grading στοιχεία και σωστό context.',
                            'sections' => [
                                ['title' => 'Καθαρές φωτογραφίες front / back', 'text' => 'Για slabs και raw cards χρειάζονται καθαρές γωνίες, centering και επιφάνεια.'],
                                ['title' => 'Σωστό set και card number', 'text' => 'Ο αγοραστής πρέπει να καταλαβαίνει αμέσως ποιο ακριβώς κομμάτι βλέπει.'],
                                ['title' => 'Χωρίς τοίχο κειμένου', 'text' => 'Ακόμα και στα loot lots, η compact παρουσίαση που δείχνει hits και themes πουλάει καλύτερα.'],
                            ],
                        ],
                        'en' => [
                            'intro' => 'Strong card listings are not only about price. They depend on photos, grading details and clean context.',
                            'sections' => [
                                ['title' => 'Clean front / back photography', 'text' => 'For slabs and raw cards you need clean corners, centering and surface views.'],
                                ['title' => 'Correct set and card number', 'text' => 'The buyer should immediately understand which exact piece is being offered.'],
                                ['title' => 'No wall of text', 'text' => 'Even in loot lots, a compact presentation that shows hits and themes converts better.'],
                            ],
                        ],
                    ],
                ],
                'translations' => [
                    'el' => ['title' => 'Τι κάνει μια αγγελία κάρτας να δείχνει premium', 'excerpt' => 'Οι μικρές λεπτομέρειες που κάνουν graded cards, sealed boxes και raw singles να φαίνονται σοβαρά.'],
                    'en' => ['title' => 'What Makes a Card Listing Look Premium', 'excerpt' => 'The small details that make graded cards, sealed boxes and raw singles look credible and collectible.'],
                ],
            ],
            [
                'category_key' => 'selling',
                'author_key' => 'eleni',
                'slug' => 'selling-figures-with-better-protection',
                'title' => 'Selling Figures with Better Protection',
                'excerpt' => 'How to document box condition, accessories and shipping protection for premium figures.',
                'published_days_ago' => 6,
                'is_featured' => false,
                'tags' => ['Figures', 'Shipping', 'Seller Tips'],
                'cover_media' => ['url' => '/storage/seed/blog/figure-protection.jpg', 'kind' => 'image'],
                'content' => [
                    'translations' => [
                        'el' => [
                            'intro' => 'Οι premium φιγούρες θέλουν καθαρό listing και ακόμα καλύτερο packing. Το κουτί είναι μέρος της αξίας.',
                            'sections' => [
                                ['title' => 'Πλήρης φωτογράφιση κουτιού', 'text' => 'Corners, παράθυρα, seals και accessory trays πρέπει να φαίνονται σωστά.'],
                                ['title' => 'Σαφή στοιχεία πληρότητας', 'text' => 'Αν λείπει κάτι, πρέπει να δηλώνεται πριν την αγορά.'],
                                ['title' => 'Double-box πρακτική', 'text' => 'Οι μεγάλες φιγούρες θέλουν outer box και προστασία στις γωνίες για ασφαλή άφιξη.'],
                            ],
                        ],
                        'en' => [
                            'intro' => 'Premium figures need a clean listing and even better packing. The box is part of the value.',
                            'sections' => [
                                ['title' => 'Full box photography', 'text' => 'Corners, windows, seals and accessory trays should be clearly shown.'],
                                ['title' => 'Clear completeness notes', 'text' => 'If anything is missing, it must be stated before the sale.'],
                                ['title' => 'Double-box practice', 'text' => 'Large figures should be shipped with an outer box and reinforced corners for safer delivery.'],
                            ],
                        ],
                    ],
                ],
                'translations' => [
                    'el' => ['title' => 'Πώς πουλάς φιγούρες με καλύτερη προστασία', 'excerpt' => 'Τι να δείξεις σε box condition, αξεσουάρ και συσκευασία για premium figures.'],
                    'en' => ['title' => 'Selling Figures with Better Protection', 'excerpt' => 'How to document box condition, accessories and shipping protection for premium figures.'],
                ],
            ],
        ];
    }
}
