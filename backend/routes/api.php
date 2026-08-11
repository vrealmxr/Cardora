<?php

use App\Http\Controllers\Api\AuctionBidController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminOrderPaymentController;
use App\Http\Controllers\Api\BlogController;
use App\Http\Controllers\Api\BootstrapController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DrawCampaignController;
use App\Http\Controllers\Api\DrawEntryController;
use App\Http\Controllers\Api\EmailVerificationNotificationController;
use App\Http\Controllers\Api\EscrowTransactionController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\FeaturedListingPaymentController;
use App\Http\Controllers\Api\ForgotPasswordController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\ListingOfferController;
use App\Http\Controllers\Api\ListingController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderReleaseController;
use App\Http\Controllers\Api\ProfileCollectionController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProfileAccountController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PublicProfileController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SellerBalanceController;
use App\Http\Controllers\Api\SellerStripeAccountController;
use App\Http\Controllers\Api\ShippingPickupPointController;
use App\Http\Controllers\Api\StripeCheckoutController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Api\SupportTicketController;
use App\Http\Controllers\Api\TradeDealController;
use App\Http\Controllers\Api\TradeRequestController;
use App\Http\Controllers\Api\AdminTradeDealController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\VerificationController;
use Illuminate\Support\Facades\Route;

Route::post('/stripe/webhooks', [StripeWebhookController::class, 'handle']);

Route::middleware('set.locale')->group(function (): void {
    Route::get('/bootstrap', BootstrapController::class);

    Route::prefix('auth')->group(function (): void {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/google/code', [GoogleAuthController::class, 'code']);
        Route::get('/google/redirect', [GoogleAuthController::class, 'redirect']);
        Route::get('/google/callback', [GoogleAuthController::class, 'callback']);
        Route::post('/forgot-password', [ForgotPasswordController::class, 'store']);
        Route::post('/reset-password', [ForgotPasswordController::class, 'reset']);

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store']);
        });
    });

    Route::prefix('products')->group(function (): void {
        Route::get('/', [ProductController::class, 'index']);
        Route::get('/{product}', [ProductController::class, 'show']);
    });

    Route::prefix('categories')->group(function (): void {
        Route::get('/', [CategoryController::class, 'index']);
        Route::get('/{category}', [CategoryController::class, 'show']);
    });

    Route::prefix('listings')->group(function (): void {
        Route::get('/', [ListingController::class, 'index']);
        Route::get('/{listing}', [ListingController::class, 'show']);
    });

    Route::prefix('draws')->group(function (): void {
        Route::get('/', [DrawCampaignController::class, 'index']);
        Route::get('/{drawCampaign}/entries', [DrawEntryController::class, 'index']);
        Route::get('/{drawCampaign}', [DrawCampaignController::class, 'show']);
    });

    Route::prefix('auctions')->group(function (): void {
        Route::get('/listings/{listing}/bids', [AuctionBidController::class, 'index']);
    });

    Route::prefix('blog')->group(function (): void {
        Route::get('/', [BlogController::class, 'index']);
        Route::get('/{blogPost:slug}', [BlogController::class, 'show']);
    });

    Route::prefix('reviews')->group(function (): void {
        Route::get('/', [ReviewController::class, 'index']);
        Route::get('/{review}', [ReviewController::class, 'show']);
    });

    Route::prefix('profiles')->group(function (): void {
        Route::get('/{profile:handle}', [PublicProfileController::class, 'show']);
    });

    Route::get('/shipping/pickup-points', [ShippingPickupPointController::class, 'index']);
});

Route::middleware(['auth:sanctum', 'set.locale'])->group(function (): void {
    Route::post('/uploads', [UploadController::class, 'store']);

    Route::prefix('products')->group(function (): void {
        Route::post('/', [ProductController::class, 'store']);
        Route::put('/{product}', [ProductController::class, 'update']);
        Route::delete('/{product}', [ProductController::class, 'destroy']);
    });

    Route::prefix('categories')->group(function (): void {
        Route::post('/', [CategoryController::class, 'store']);
        Route::put('/{category}', [CategoryController::class, 'update']);
        Route::delete('/{category}', [CategoryController::class, 'destroy']);
    });

    Route::prefix('listings')->group(function (): void {
        Route::post('/', [ListingController::class, 'store']);
        Route::put('/{listing}', [ListingController::class, 'update']);
        Route::delete('/{listing}', [ListingController::class, 'destroy']);
    });

    Route::prefix('cart')->group(function (): void {
        Route::get('/', [CartController::class, 'index']);
        Route::post('/', [CartController::class, 'store']);
        Route::get('/{cart}', [CartController::class, 'show']);
        Route::put('/{cart}', [CartController::class, 'update']);
        Route::delete('/{cart}', [CartController::class, 'destroy']);
    });

    Route::prefix('favorites')->group(function (): void {
        Route::get('/', [FavoriteController::class, 'index']);
        Route::post('/', [FavoriteController::class, 'store']);
        Route::get('/{favorite}', [FavoriteController::class, 'show']);
        Route::delete('/{favorite}', [FavoriteController::class, 'destroy']);
    });

    Route::prefix('orders')->group(function (): void {
        Route::get('/', [OrderController::class, 'index']);
        Route::post('/', [OrderController::class, 'store']);
        Route::get('/{order}', [OrderController::class, 'show']);
        Route::put('/{order}', [OrderController::class, 'update']);
        Route::delete('/{order}', [OrderController::class, 'destroy']);
        Route::post('/{order}/confirm-received', [OrderReleaseController::class, 'confirmReceived']);
    });

    Route::post('/checkout/session', [StripeCheckoutController::class, 'store']);
    Route::post('/checkout/session/confirm', [StripeCheckoutController::class, 'confirm']);
    Route::post('/featured-listings/checkout', [FeaturedListingPaymentController::class, 'startCheckout']);
    Route::post('/featured-listings/confirm', [FeaturedListingPaymentController::class, 'confirm']);

    Route::prefix('seller/connect')->group(function (): void {
        Route::get('/account', [SellerStripeAccountController::class, 'show']);
        Route::post('/onboarding/start', [SellerStripeAccountController::class, 'startOnboarding']);
        Route::post('/dashboard-link', [SellerStripeAccountController::class, 'dashboardLink']);
    });

    Route::get('/seller/balance/summary', [SellerBalanceController::class, 'summary']);
    Route::get('/seller/payouts/history', [SellerBalanceController::class, 'payoutHistory']);

    Route::prefix('admin/orders')->group(function (): void {
        Route::get('/payments', [AdminOrderPaymentController::class, 'index']);
        Route::post('/{order}/manual-release', [AdminOrderPaymentController::class, 'manualRelease']);
        Route::post('/{order}/refund', [AdminOrderPaymentController::class, 'refund']);
    });

    Route::prefix('messages')->group(function (): void {
        Route::get('/', [MessageController::class, 'index']);
        Route::post('/conversations', [MessageController::class, 'storeConversation']);
        Route::get('/conversations/{conversation}', [MessageController::class, 'show']);
        Route::post('/conversations/{conversation}/read', [MessageController::class, 'markConversationRead']);
        Route::post('/conversations/{conversation}/offers', [ListingOfferController::class, 'store']);
        Route::post('/', [MessageController::class, 'store']);
        Route::put('/{message}', [MessageController::class, 'update']);
        Route::delete('/{message}', [MessageController::class, 'destroy']);
    });

    Route::prefix('listing-offers')->group(function (): void {
        Route::post('/{listingOffer}/counter', [ListingOfferController::class, 'counter']);
        Route::post('/{listingOffer}/accept', [ListingOfferController::class, 'accept']);
        Route::post('/{listingOffer}/reject', [ListingOfferController::class, 'reject']);
    });

    Route::prefix('profile')->group(function (): void {
        Route::get('/', [ProfileController::class, 'show']);
        Route::put('/', [ProfileController::class, 'update']);
        Route::put('/account/email', [ProfileAccountController::class, 'updateEmail']);
        Route::put('/account/password', [ProfileAccountController::class, 'updatePassword']);
        Route::get('/collection', [ProfileCollectionController::class, 'index']);
        Route::post('/collection', [ProfileCollectionController::class, 'store']);
        Route::put('/collection/{collectionEntry}', [ProfileCollectionController::class, 'update']);
        Route::delete('/collection/{collectionEntry}', [ProfileCollectionController::class, 'destroy']);
        Route::get('/verification', [VerificationController::class, 'show']);
        Route::post('/verification', [VerificationController::class, 'store']);
        Route::put('/verification/{verificationSubmission}', [VerificationController::class, 'update']);
    });

    Route::prefix('profiles')->group(function (): void {
        Route::post('/{profile:handle}/like', [PublicProfileController::class, 'like']);
        Route::delete('/{profile:handle}/like', [PublicProfileController::class, 'unlike']);
        Route::post('/{profile:handle}/follow', [PublicProfileController::class, 'follow']);
        Route::delete('/{profile:handle}/follow', [PublicProfileController::class, 'unfollow']);
    });

    Route::prefix('support')->group(function (): void {
        Route::get('/', [SupportTicketController::class, 'index']);
        Route::post('/', [SupportTicketController::class, 'store']);
        Route::get('/{supportTicket}', [SupportTicketController::class, 'show']);
        Route::put('/{supportTicket}', [SupportTicketController::class, 'update']);
        Route::delete('/{supportTicket}', [SupportTicketController::class, 'destroy']);
    });

    Route::prefix('trades')->group(function (): void {
        Route::get('/requests', [TradeRequestController::class, 'index']);
        Route::post('/requests', [TradeRequestController::class, 'store']);
        Route::post('/requests/{tradeRequest}/accept', [TradeRequestController::class, 'accept']);
        Route::post('/requests/{tradeRequest}/reject', [TradeRequestController::class, 'reject']);
        Route::post('/requests/{tradeRequest}/cancel', [TradeRequestController::class, 'cancel']);

        Route::get('/deals', [TradeDealController::class, 'index']);
        Route::get('/deals/{tradeDeal}', [TradeDealController::class, 'show']);
        Route::post('/deals/{tradeDeal}/checkout-session', [TradeDealController::class, 'checkoutSession']);
        Route::post('/deals/{tradeDeal}/release', [TradeDealController::class, 'release']);
        Route::post('/deals/{tradeDeal}/dispute', [TradeDealController::class, 'dispute']);

        Route::prefix('admin')->group(function (): void {
            Route::get('/deals', [AdminTradeDealController::class, 'index']);
            Route::post('/deals/{tradeDeal}/resolve', [AdminTradeDealController::class, 'resolve']);
        });
    });

    Route::prefix('escrow')->group(function (): void {
        Route::get('/', [EscrowTransactionController::class, 'index']);
        Route::post('/', [EscrowTransactionController::class, 'store']);
        Route::get('/{escrowTransaction}', [EscrowTransactionController::class, 'show']);
        Route::put('/{escrowTransaction}', [EscrowTransactionController::class, 'update']);
        Route::delete('/{escrowTransaction}', [EscrowTransactionController::class, 'destroy']);
    });

    Route::prefix('draws')->group(function (): void {
        Route::post('/', [DrawCampaignController::class, 'store']);
        Route::post('/{drawCampaign}/entries', [DrawEntryController::class, 'store']);
        Route::put('/{drawCampaign}', [DrawCampaignController::class, 'update']);
        Route::delete('/{drawCampaign}', [DrawCampaignController::class, 'destroy']);
    });

    Route::prefix('auctions')->group(function (): void {
        Route::post('/listings/{listing}/bids', [AuctionBidController::class, 'store']);
    });

    Route::prefix('blog')->group(function (): void {
        Route::post('/', [BlogController::class, 'store']);
        Route::put('/{blogPost}', [BlogController::class, 'update']);
        Route::delete('/{blogPost}', [BlogController::class, 'destroy']);
    });

    Route::prefix('notifications')->group(function (): void {
        Route::get('/', [NotificationController::class, 'index']);
        Route::put('/{notification}', [NotificationController::class, 'update']);
        Route::post('/mark-all-read', [NotificationController::class, 'markAllRead']);
        Route::delete('/{notification}', [NotificationController::class, 'destroy']);
    });

    Route::prefix('reviews')->group(function (): void {
        Route::post('/', [ReviewController::class, 'store']);
        Route::put('/{review}', [ReviewController::class, 'update']);
        Route::delete('/{review}', [ReviewController::class, 'destroy']);
    });
});
