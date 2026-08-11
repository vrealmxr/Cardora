<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreListingRequest;
use App\Http\Requests\UpdateListingRequest;
use App\Http\Resources\ListingResource;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Product;
use App\Models\Category;
use App\Models\FeaturedListingPayment;
use App\Services\FeaturedListingPaymentService;
use App\Services\MarketplaceAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ListingController extends Controller
{
    public function index(Request $request, MarketplaceAccessService $marketplaceAccess)
    {
        $listings = Listing::query()
            ->with(['product.category', 'category', 'seller', 'winningBidder'])
            ->withCount('bids')
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->filled('seller_id'), fn ($query) => $query->where('seller_id', $request->integer('seller_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('sale_format'), fn ($query) => $query->where('sale_format', $request->string('sale_format')))
            ->when($request->boolean('featured'), function ($query) {
                $query->whereNotNull('featured_until')->where('featured_until', '>=', now());
            })
            ->latest()
            ->paginate($request->integer('per_page', 20));

        $isOwnListingIndex = $request->user()
            && $request->filled('seller_id')
            && (int) $request->integer('seller_id') === (int) $request->user()->getKey();

        if (! $isOwnListingIndex) {
            $listings->setCollection(
                $listings->getCollection()
                    ->filter(fn (Listing $listing) => $marketplaceAccess->listingIsPubliclyVisible($listing))
                    ->values()
            );
        }

        return ListingResource::collection($listings);
    }

    public function store(
        StoreListingRequest $request,
        MarketplaceAccessService $marketplaceAccess,
        FeaturedListingPaymentService $featuredService
    )
    {
        $marketplaceAccess->assertCanSell($request->user(), app()->getLocale());

        $validated = $request->validated();
        $this->assertSaleFormatCategoryCompatibility(
            $validated['sale_format'] ?? null,
            (int) $validated['category_id']
        );
        $validated = $this->normalizeListingPayloadForSaleFormat($validated);
        $featuredPaymentId = $validated['featured_payment_id'] ?? null;
        unset($validated['featured_payment_id']);
        $validated['is_featured'] = false;
        $validated['featured_until'] = null;
        $validated['featured_payment_id'] = null;

        $listing = DB::transaction(function () use ($request, $validated) {
            $productPayload = $validated['product'] ?? null;
            unset($validated['product']);

            if ($productPayload) {
                $product = Product::create([
                    ...$productPayload,
                    'category_id' => $validated['category_id'],
                    'slug' => $this->generateUniqueProductSlug($productPayload['title']),
                ]);

                $validated['product_id'] = $product->getKey();
            }

            $validated['seller_id'] = $request->user()->getKey();
            $validated['title_snapshot'] = $validated['title_snapshot']
                ?? $productPayload['title']
                ?? Product::query()->find($validated['product_id'])?->title;
            $validated['status'] = $validated['status'] ?? 'pending_review';

            return Listing::create($validated);
        });

        if ($featuredPaymentId) {
            $payment = FeaturedListingPayment::query()
                ->where('id', $featuredPaymentId)
                ->where('user_id', $request->user()->getKey())
                ->whereIn('status', ['paid', 'used'])
                ->first();

            if ($payment && $payment->status === 'paid' && ! $payment->used_at) {
                $featuredService->attachPaymentToListing($payment, $listing);
            }
        }

        return (new ListingResource($listing->load(['product.category', 'category', 'seller', 'winningBidder'])->loadCount('bids')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Listing $listing, MarketplaceAccessService $marketplaceAccess)
    {
        $canViewHiddenListing = (bool) $request->user()
            && (
                (int) $request->user()->getKey() === (int) $listing->seller_id
                || (bool) $request->user()->is_admin
            );

        if (! $canViewHiddenListing && ! $marketplaceAccess->listingIsPubliclyVisible($listing->loadMissing('seller'))) {
            abort(404);
        }

        return new ListingResource(
            $listing->load(['product.category', 'category', 'seller', 'favorites', 'cartItems', 'bids.bidder', 'winningBidder'])->loadCount('bids')
        );
    }

    public function update(
        UpdateListingRequest $request,
        Listing $listing,
        MarketplaceAccessService $marketplaceAccess,
        FeaturedListingPaymentService $featuredService
    )
    {
        abort_unless(
            $listing->seller_id === $request->user()->getKey(),
            403,
            __('api.errors.forbidden')
        );

        $marketplaceAccess->assertCanSell($request->user(), app()->getLocale());

        $validated = $request->validated();
        $nextSaleFormat = $validated['sale_format'] ?? $listing->sale_format;
        $nextCategoryId = (int) ($validated['category_id'] ?? $listing->category_id);
        $this->assertSaleFormatCategoryCompatibility($nextSaleFormat, $nextCategoryId);
        $validated = $this->normalizeListingPayloadForSaleFormat($validated, $nextSaleFormat);
        $featuredPaymentId = $validated['featured_payment_id'] ?? null;
        unset($validated['featured_payment_id']);
        $productPayload = $validated['product'] ?? null;
        unset($validated['product']);

        DB::transaction(function () use ($listing, $validated, $productPayload) {
            if ($productPayload && $listing->product) {
                $listing->product->update($productPayload);
            }

            $listing->update($validated);
        });

        if ($featuredPaymentId) {
            $payment = FeaturedListingPayment::query()
                ->where('id', $featuredPaymentId)
                ->where('user_id', $request->user()->getKey())
                ->whereIn('status', ['paid', 'used'])
                ->first();

            if ($payment && $payment->status === 'paid' && ! $payment->used_at) {
                $featuredService->attachPaymentToListing($payment, $listing);
            }
        }

        return new ListingResource($listing->fresh()->load(['product.category', 'category', 'seller', 'winningBidder'])->loadCount('bids'));
    }

    public function destroy(Listing $listing)
    {
        abort_unless(
            $listing->seller_id === request()->user()->getKey(),
            403,
            __('api.errors.forbidden')
        );

        $listing->delete();

        return response()->noContent();
    }

    protected function generateUniqueProductSlug(string $title): string
    {
        $baseSlug = Str::slug($title) ?: 'product';
        $slug = $baseSlug;
        $suffix = 1;

        while (Product::query()->where('slug', $slug)->exists()) {
            $slug = sprintf('%s-%d', $baseSlug, $suffix);
            $suffix++;
        }

        return $slug;
    }

    protected function assertSaleFormatCategoryCompatibility(?string $saleFormat, int $categoryId): void
    {
        if (strtolower((string) $saleFormat) !== 'trade') {
            return;
        }

        $category = Category::query()->find($categoryId);
        $slug = strtolower((string) $category?->slug);
        $frontendKey = strtolower((string) data_get($category?->metadata, 'frontend_key', ''));

        if (! in_array($slug, ['kartes', 'cards'], true) && $frontendKey !== 'cards') {
            throw ValidationException::withMessages([
                'category_id' => ['Trade listings are available only in the cards category.'],
            ]);
        }
    }

    protected function normalizeListingPayloadForSaleFormat(array $payload, ?string $explicitSaleFormat = null): array
    {
        $saleFormat = strtolower((string) ($explicitSaleFormat ?? ($payload['sale_format'] ?? 'fixed_price')));
        $payload['sale_format'] = $saleFormat;

        if ($saleFormat === 'trade') {
            $payload['quantity'] = 1;
            $payload['available_quantity'] = 1;
            $payload['accept_offers'] = false;
            $payload['starting_bid'] = null;
            $payload['current_bid'] = null;
            $payload['reserve_price'] = null;
            $payload['bid_increment'] = null;
            $payload['buyout_price'] = null;
            $payload['auction_starts_at'] = null;
            $payload['auction_ends_at'] = null;
            $payload['winning_bidder_id'] = null;
            $payload['auction_settings'] = null;
        } elseif ($saleFormat === 'fixed_price') {
            $payload['starting_bid'] = null;
            $payload['current_bid'] = null;
            $payload['reserve_price'] = null;
            $payload['bid_increment'] = null;
            $payload['buyout_price'] = null;
            $payload['auction_starts_at'] = null;
            $payload['auction_ends_at'] = null;
            $payload['winning_bidder_id'] = null;
            $payload['auction_settings'] = null;
        } elseif ($saleFormat === 'auction') {
            $payload['quantity'] = 1;
            $payload['available_quantity'] = 1;
            $payload['accept_offers'] = false;
        } else {
            $payload['sale_format'] = 'fixed_price';
        }

        return $payload;
    }
}
