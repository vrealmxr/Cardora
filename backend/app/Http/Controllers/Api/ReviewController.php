<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReviewRequest;
use App\Http\Requests\UpdateReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use App\Services\MarketplaceNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $reviews = Review::query()
            ->with(['reviewer', 'reviewee', 'listing.product.category', 'product.category'])
            ->when($request->boolean('public_only', true), fn ($query) => $query->where('is_public', true))
            ->when($request->filled('reviewee_id'), fn ($query) => $query->where('reviewee_id', $request->integer('reviewee_id')))
            ->when($request->filled('reviewer_id'), fn ($query) => $query->where('reviewer_id', $request->integer('reviewer_id')))
            ->when($request->filled('listing_id'), fn ($query) => $query->where('listing_id', $request->integer('listing_id')))
            ->when($request->filled('product_id'), fn ($query) => $query->where('product_id', $request->integer('product_id')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return ReviewResource::collection($reviews);
    }

    public function store(
        StoreReviewRequest $request,
        MarketplaceNotificationService $notifications
    ) {
        $actor = $request->user();
        $validated = $request->validated();
        $order = array_key_exists('order_id', $validated)
            ? Order::query()->findOrFail($validated['order_id'])
            : null;

        $revieweeId = $this->resolveRevieweeId($actor->getKey(), $validated, $order);

        $review = DB::transaction(function () use ($actor, $validated, $revieweeId) {
            $review = Review::updateOrCreate(
                [
                    'order_id' => $validated['order_id'] ?? null,
                    'reviewer_id' => $actor->getKey(),
                    'reviewee_id' => $revieweeId,
                ],
                [
                    'listing_id' => $validated['listing_id'] ?? null,
                    'product_id' => $validated['product_id'] ?? null,
                    'rating' => $validated['rating'],
                    'title' => $validated['title'] ?? null,
                    'body' => $validated['body'] ?? null,
                    'is_public' => $validated['is_public'] ?? true,
                ]
            );

            $this->syncRevieweeRating($review->reviewee);

            return $review->load(['reviewer', 'reviewee', 'listing.product.category', 'product.category']);
        });

        $notifications->createForUser(
            $revieweeId,
            'review_received',
            __('api.notifications.review_received_title'),
            __('api.notifications.review_received_body', [
                'rating' => $review->rating,
                'reviewer' => $actor->display_name ?: $actor->name,
            ]),
            ['review_id' => $review->getKey()],
            'orders'
        );

        return response()->json([
            'message' => __('api.reviews.created'),
            'data' => new ReviewResource($review),
        ], 201);
    }

    public function show(Review $review)
    {
        return new ReviewResource(
            $review->load(['reviewer', 'reviewee', 'listing.product.category', 'product.category'])
        );
    }

    public function update(UpdateReviewRequest $request, Review $review)
    {
        abort_unless(
            $review->reviewer_id === $request->user()->getKey(),
            403,
            __('api.errors.forbidden')
        );

        $review->update($request->validated());
        $this->syncRevieweeRating($review->reviewee);

        return response()->json([
            'message' => __('api.reviews.updated'),
            'data' => new ReviewResource(
                $review->fresh()->load(['reviewer', 'reviewee', 'listing.product.category', 'product.category'])
            ),
        ]);
    }

    public function destroy(Request $request, Review $review)
    {
        abort_unless(
            $review->reviewer_id === $request->user()->getKey(),
            403,
            __('api.errors.forbidden')
        );

        $reviewee = $review->reviewee;
        $review->delete();
        $this->syncRevieweeRating($reviewee);

        return response()->noContent();
    }

    protected function resolveRevieweeId(int $actorId, array $validated, ?Order $order): int
    {
        if ($order) {
            abort_unless(
                in_array($actorId, [$order->buyer_id, $order->seller_id], true),
                403,
                __('api.errors.forbidden')
            );

            return $order->buyer_id === $actorId ? (int) $order->seller_id : (int) $order->buyer_id;
        }

        $revieweeId = $validated['reviewee_id'] ?? null;

        if (! $revieweeId || (int) $revieweeId === $actorId) {
            throw ValidationException::withMessages([
                'reviewee_id' => [__('api.reviews.invalid_reviewee')],
            ]);
        }

        return (int) $revieweeId;
    }

    protected function syncRevieweeRating(User $reviewee): void
    {
        $averageRating = Review::query()
            ->where('reviewee_id', $reviewee->getKey())
            ->where('is_public', true)
            ->avg('rating');

        $reviewee->update([
            'rating' => round((float) ($averageRating ?? 0), 2),
        ]);
    }
}
