<?php

namespace App\Console\Commands;

use App\Models\Listing;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DuplicateSoldListings extends Command
{
    protected $signature = 'marketplace:duplicate-sold-listings
        {--ids=* : Specific sold listing IDs to duplicate}
        {--status=published : Status for the duplicated listings}';

    protected $description = 'Duplicate sold fixed-price listings as brand-new public listings without affecting order history.';

    public function handle(): int
    {
        $targetStatus = (string) $this->option('status');
        $allowedStatuses = ['active', 'published', 'pending_review'];

        if (! in_array($targetStatus, $allowedStatuses, true)) {
            $this->error(sprintf(
                'Invalid --status value "%s". Allowed: %s',
                $targetStatus,
                implode(', ', $allowedStatuses)
            ));

            return self::FAILURE;
        }

        $requestedIds = collect($this->option('ids'))
            ->map(fn (mixed $id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        $query = Listing::query()
            ->with('product')
            ->where('status', 'sold')
            ->where('sale_format', 'fixed_price')
            ->orderBy('id');

        if ($requestedIds !== []) {
            $query->whereIn('id', $requestedIds);
        }

        $soldListings = $query->get();

        if ($soldListings->isEmpty()) {
            $this->warn('No sold fixed-price listings matched the current filters.');

            return self::SUCCESS;
        }

        $duplicates = collect();

        foreach ($soldListings as $listing) {
            $duplicate = DB::transaction(function () use ($listing, $targetStatus) {
                $freshListing = Listing::query()
                    ->with('product')
                    ->lockForUpdate()
                    ->findOrFail($listing->getKey());

                $product = $freshListing->product;

                $newProduct = $product
                    ? $this->duplicateProduct($product, $freshListing->category_id)
                    : null;

                $newListing = $freshListing->replicate();

                $newListing->product_id = $newProduct?->getKey();
                $newListing->status = $targetStatus;
                $newListing->availability = 'in_stock';
                $newListing->available_quantity = max((int) ($freshListing->quantity ?? 0), 1);
                $newListing->winning_bidder_id = null;
                $newListing->current_bid = null;
                $newListing->published_at = now();
                $newListing->followers_notified_at = null;
                $newListing->auction_starts_at = null;
                $newListing->auction_ends_at = null;
                $newListing->lot_snapshot = $this->resetLotData($freshListing->lot_snapshot);
                $newListing->compliance_flags = array_merge(
                    is_array($freshListing->compliance_flags) ? $freshListing->compliance_flags : [],
                    [
                        'duplicated_from_listing_id' => (int) $freshListing->getKey(),
                        'duplicated_at' => now()->toIso8601String(),
                    ]
                );
                $newListing->created_at = now();
                $newListing->updated_at = now();
                $newListing->save();

                if ($newProduct && is_array($newProduct->lot_configuration)) {
                    $newProduct->forceFill([
                        'lot_configuration' => $this->resetLotData($newProduct->lot_configuration),
                    ])->save();
                }

                return $newListing->fresh(['product']);
            });

            $duplicates->push($duplicate);
        }

        $this->info(sprintf('Created %d duplicated listing(s).', $duplicates->count()));
        $this->newLine();
        $this->table(
            ['Source listing', 'New listing', 'Title', 'Status'],
            $duplicates->map(fn (Listing $duplicate) => [
                Arr::get($duplicate->compliance_flags ?? [], 'duplicated_from_listing_id'),
                $duplicate->getKey(),
                $duplicate->title_snapshot ?: $duplicate->product?->title ?: sprintf('Listing #%d', $duplicate->getKey()),
                $duplicate->status,
            ])->all()
        );

        return self::SUCCESS;
    }

    protected function duplicateProduct(Product $product, int $categoryId): Product
    {
        $newProduct = $product->replicate();
        $newProduct->category_id = $categoryId;
        $newProduct->slug = $this->generateUniqueProductSlug($product->title ?: 'product');
        $newProduct->sku = null;
        $newProduct->lot_configuration = $this->resetLotData($product->lot_configuration);
        $newProduct->created_at = now();
        $newProduct->updated_at = now();
        $newProduct->save();

        return $newProduct;
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

    protected function resetLotData(mixed $lotData): mixed
    {
        if (! is_array($lotData)) {
            return $lotData;
        }

        if (! isset($lotData['individual_cards']) || ! is_array($lotData['individual_cards'])) {
            return $lotData;
        }

        $lotData['individual_cards'] = collect($lotData['individual_cards'])
            ->map(function (mixed $card) {
                if (! is_array($card)) {
                    return $card;
                }

                $card['status'] = 'available';
                unset(
                    $card['reserved_order_id'],
                    $card['reserved_at'],
                    $card['sold_order_id'],
                    $card['sold_at']
                );

                return $card;
            })
            ->values()
            ->all();

        return $lotData;
    }
}
