<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Listing;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarketplaceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_login_fetch_profile_and_logout(): void
    {
        $registerResponse = $this->withHeader('X-Locale', 'en')->postJson('/api/auth/register', [
            'name' => 'Andreas Maniatis',
            'display_name' => 'Andreas',
            'email' => 'andreas@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'locale' => 'en',
            'device_name' => 'feature-suite',
        ]);

        $registerResponse
            ->assertCreated()
            ->assertJsonPath('message', 'Your account was created successfully.')
            ->assertJsonPath('data.locale', 'en');

        $token = $registerResponse->json('token');

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'andreas@example.com');

        $this->withHeader('X-Locale', 'el')->postJson('/api/auth/login', [
            'email' => 'andreas@example.com',
            'password' => 'secret123',
            'device_name' => 'feature-suite-login',
        ])->assertOk()->assertJsonPath('message', 'Η είσοδος ολοκληρώθηκε επιτυχώς.');

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Η αποσύνδεση ολοκληρώθηκε επιτυχώς.');
    }

    public function test_login_invalidates_previous_tokens_for_the_same_user(): void
    {
        $user = User::factory()->create([
            'email' => 'single-session@example.com',
        ]);

        $firstLogin = $this->postJson('/api/auth/login', [
            'email' => 'single-session@example.com',
            'password' => 'password',
            'device_name' => 'device-a',
        ]);

        $firstLogin->assertOk();
        $firstToken = (string) $firstLogin->json('token');

        $secondLogin = $this->postJson('/api/auth/login', [
            'email' => 'single-session@example.com',
            'password' => 'password',
            'device_name' => 'device-b',
        ]);

        $secondLogin->assertOk();
        $secondToken = (string) $secondLogin->json('token');

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withToken($firstToken)
            ->getJson('/api/auth/me')
            ->assertUnauthorized();

        $this->withToken($firstToken)
            ->getJson('/api/bootstrap')
            ->assertUnauthorized();

        $this->withToken($secondToken)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->getKey());
    }

    public function test_authenticated_user_can_manage_cart_and_favorites(): void
    {
        $buyer = User::factory()->create();
        $listing = $this->createListing();

        Sanctum::actingAs($buyer);

        $addCartResponse = $this->postJson('/api/cart', [
            'listing_id' => $listing->getKey(),
            'quantity' => 2,
        ]);

        $addCartResponse
            ->assertCreated()
            ->assertJsonPath('data.quantity', 2);

        $cartId = $addCartResponse->json('data.id');

        $this->getJson('/api/cart')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->putJson("/api/cart/{$cartId}", [
            'quantity' => 1,
        ])->assertOk()->assertJsonPath('data.quantity', 1);

        $favoriteResponse = $this->postJson('/api/favorites', [
            'listing_id' => $listing->getKey(),
        ]);

        $favoriteResponse->assertCreated();
        $favoriteId = $favoriteResponse->json('data.id');

        $this->getJson('/api/favorites')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->deleteJson("/api/favorites/{$favoriteId}")
            ->assertOk()
            ->assertJsonPath('message', 'Η αγγελία αφαιρέθηκε από τα αγαπημένα.');

        $this->deleteJson("/api/cart/{$cartId}")
            ->assertOk()
            ->assertJsonPath('message', 'Το αντικείμενο αφαιρέθηκε από το καλάθι.');
    }

    public function test_checkout_creates_order_items_escrow_notifications_and_clears_cart(): void
    {
        $buyer = User::factory()->create();
        $listing = $this->createListing([
            'price' => 150,
            'quantity' => 3,
            'available_quantity' => 3,
            'shipping_cost' => 9,
        ]);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/cart', [
            'listing_id' => $listing->getKey(),
            'quantity' => 2,
        ])->assertCreated();

        $response = $this->postJson('/api/orders', [
            'service_fee' => 5,
            'shipping_address' => [
                'name' => 'Ανδρέας Μανιάτης',
                'city' => 'Αθήνα',
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.escrow_status', 'pending_payment');

        $orderId = $response->json('data.id');

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'buyer_id' => $buyer->getKey(),
            'seller_id' => $listing->seller_id,
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'listing_id' => $listing->getKey(),
            'quantity' => 2,
        ]);

        $this->assertDatabaseHas('escrow_transactions', [
            'order_id' => $orderId,
            'buyer_id' => $buyer->getKey(),
            'seller_id' => $listing->seller_id,
            'status' => 'pending_payment',
        ]);

        $this->assertDatabaseCount('cart_items', 0);

        $listing->refresh();
        $this->assertSame(1, $listing->available_quantity);
        $this->assertSame(2, Notification::query()->count());
    }

    public function test_users_can_start_conversation_send_messages_and_manage_notifications(): void
    {
        $buyer = User::factory()->create(['name' => 'Buyer Test']);
        $seller = User::factory()->create(['name' => 'Seller Test']);
        $listing = $this->createListing(['seller_id' => $seller->getKey()]);

        Sanctum::actingAs($buyer);

        $conversationResponse = $this->postJson('/api/messages/conversations', [
            'listing_id' => $listing->getKey(),
            'initial_message' => 'Γεια σου, είναι διαθέσιμο;',
        ]);

        $conversationResponse->assertCreated();
        $conversationId = $conversationResponse->json('data.id');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $seller->getKey(),
            'type' => 'message_received',
        ]);

        Sanctum::actingAs($seller);

        $this->postJson('/api/messages', [
            'conversation_id' => $conversationId,
            'body' => 'Ναι, είναι ακόμα διαθέσιμο.',
        ])->assertCreated();

        Sanctum::actingAs($buyer);

        $this->getJson("/api/messages/conversations/{$conversationId}")
            ->assertOk()
            ->assertJsonCount(2, 'data.messages');

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->postJson('/api/notifications/mark-all-read')
            ->assertOk()
            ->assertJsonPath('message', 'Όλες οι ειδοποιήσεις σημειώθηκαν ως αναγνωσμένες.');

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $buyer->getKey(),
            'read_at' => null,
        ]);
    }

    public function test_user_can_leave_review_and_update_profile_locale(): void
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->create();

        $order = Order::create([
            'buyer_id' => $buyer->getKey(),
            'seller_id' => $seller->getKey(),
            'order_number' => 'CDR-REVIEW-001',
            'status' => 'completed',
            'escrow_status' => 'released',
            'subtotal' => 90,
            'shipping_total' => 5,
            'service_fee' => 3,
            'total' => 98,
            'currency' => 'EUR',
        ]);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/reviews', [
            'order_id' => $order->getKey(),
            'rating' => 5,
            'title' => 'Άψογη συναλλαγή',
            'body' => 'Γρήγορη αποστολή και σωστή περιγραφή.',
        ])->assertCreated()->assertJsonPath('data.reviewee_id', $seller->getKey());

        $seller->refresh();
        $this->assertSame('5.00', $seller->rating);

        $this->putJson('/api/profile', [
            'locale' => 'en',
            'city' => 'Athens',
        ])->assertOk()->assertJsonPath('data.locale', 'en');
    }

    public function test_user_can_submit_support_and_verification_requests(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/support', [
            'subject' => 'Θέλω βοήθεια με παραγγελία',
            'category' => 'order_issue',
            'description' => 'Η κατάσταση δεν ενημερώθηκε σωστά.',
        ])->assertCreated()->assertJsonPath('data.user_id', $user->getKey());

        $this->postJson('/api/profile/verification', [
            'verification_type' => 'identity',
            'payload' => [
                'country' => 'GR',
                'document_number' => 'AB123456',
            ],
            'documents' => [
                [
                    'document_type' => 'id_front',
                    'storage_path' => 'verifications/id-front.jpg',
                    'original_name' => 'id-front.jpg',
                ],
            ],
        ])->assertCreated()->assertJsonPath('data.user_id', $user->getKey());

        $this->assertDatabaseCount('support_tickets', 1);
        $this->assertDatabaseCount('verification_submissions', 1);
    }

    protected function createListing(array $overrides = []): Listing
    {
        $seller = User::factory()->create();
        $slug = 'listing-' . Str::lower(Str::random(8));
        $category = Category::create([
            'name' => 'Κάρτες',
            'slug' => 'kartes-' . Str::lower(Str::random(6)),
        ]);

        $product = Product::create([
            'category_id' => $category->getKey(),
            'title' => 'Lugia V Alt Art PSA 10',
            'slug' => 'lugia-' . Str::lower(Str::random(6)),
            'franchise' => 'Pokemon',
            'series' => 'Silver Tempest',
            'brand' => 'Pokemon',
            'language' => 'English',
        ]);

        return Listing::create([
            'product_id' => $product->getKey(),
            'category_id' => $category->getKey(),
            'seller_id' => $seller->getKey(),
            'title_snapshot' => 'Lugia V Alt Art PSA 10',
            'price' => 100,
            'quantity' => 5,
            'available_quantity' => 5,
            'condition' => 'Mint',
            'status' => 'active',
            'sale_format' => 'fixed_price',
            'shipping_cost' => 7,
            'availability' => 'available',
            ...$overrides,
        ]);
    }
}
