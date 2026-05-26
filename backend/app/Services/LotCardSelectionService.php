<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\OrderItem;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LotCardSelectionService
{
    public const ITEM_MODE = 'lot_individual_cards';

    public function allowsIndividualPurchase(Listing $listing): bool
    {
        return (bool) Arr::get($this->getLotData($listing), 'allow_individual_purchase', false);
    }

    public function getIndividualCards(Listing $listing, bool $includeUnavailable = false): array
    {
        $cards = collect(Arr::get($this->getLotData($listing), 'individual_cards', []))
            ->map(fn (mixed $card, int $index) => $this->normalizeCard($card, $index))
            ->filter()
            ->values();

        if ($includeUnavailable) {
            return $cards->all();
        }

        return $cards
            ->filter(fn (array $card) => $card['status'] === 'available')
            ->values()
            ->all();
    }

    public function getPublicLotPayload(Listing $listing, mixed $product = null): ?array
    {
        $lotData = $this->getLotData($listing, $product);

        if ($lotData === []) {
            return null;
        }

        $previewCards = collect(Arr::get($lotData, 'preview_cards', Arr::get($lotData, 'named_cards', Arr::get($lotData, 'highlights', []))))
            ->filter()
            ->values()
            ->all();
        $totalCards = (int) Arr::get($lotData, 'total_cards', Arr::get($lotData, 'totalCards', count($previewCards)));
        $individualCards = $this->getIndividualCards($listing);
        $hasUnavailableCards = collect($this->getIndividualCards($listing, true))
            ->contains(fn (array $card) => $card['status'] !== 'available');

        return [
            'totalCards' => $totalCards,
            'guaranteedHits' => (int) Arr::get($lotData, 'guaranteed_hits', Arr::get($lotData, 'guaranteedHits', 0)),
            'previewCards' => $previewCards,
            'themes' => Arr::get($lotData, 'themes', []),
            'note' => Arr::get($lotData, 'summary', Arr::get($lotData, 'note')),
            'overflowCount' => max($totalCards - count($previewCards), 0),
            'allowsIndividualPurchase' => $this->allowsIndividualPurchase($listing),
            'wholeLotPurchaseAvailable' => ! $this->allowsIndividualPurchase($listing) || ! $hasUnavailableCards,
            'individualCards' => array_values(array_map(fn (array $card) => [
                'id' => $card['id'],
                'title' => $card['title'],
                'price' => $card['price'],
            ], $individualCards)),
            'availableIndividualCardsCount' => count($individualCards),
        ];
    }

    public function assertWholeLotPurchaseAllowed(Listing $listing): void
    {
        if (! $this->allowsIndividualPurchase($listing)) {
            return;
        }

        $hasUnavailableCards = collect($this->getIndividualCards($listing, true))
            ->contains(fn (array $card) => $card['status'] !== 'available');

        if ($hasUnavailableCards) {
            throw ValidationException::withMessages([
                'items' => [__('api.cart.lot_whole_purchase_unavailable')],
            ]);
        }
    }

    public function buildSelectionSnapshot(Listing $listing, array $requestedCardIds): array
    {
        if (! $this->allowsIndividualPurchase($listing)) {
            throw ValidationException::withMessages([
                'listing_id' => [__('api.cart.lot_individual_purchase_disabled')],
            ]);
        }

        $normalizedIds = collect($requestedCardIds)
            ->map(fn (mixed $value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values();

        if ($normalizedIds->isEmpty()) {
            throw ValidationException::withMessages([
                'metadata.lot_card_ids' => [__('api.cart.lot_selection_required')],
            ]);
        }

        $cardsById = collect($this->getIndividualCards($listing, true))->keyBy('id');
        $selectedCards = $normalizedIds
            ->map(function (string $cardId) use ($cardsById) {
                $card = $cardsById->get($cardId);

                if (! $card || $card['status'] !== 'available') {
                    throw ValidationException::withMessages([
                        'metadata.lot_card_ids' => [__('api.cart.lot_card_unavailable')],
                    ]);
                }

                return $card;
            })
            ->values();

        $selectionKey = $this->makeSelectionKey($selectedCards->pluck('id')->all());
        $selectedCount = $selectedCards->count();
        $selectionTotal = round($selectedCards->sum('price'), 2);

        return [
            'item_mode' => self::ITEM_MODE,
            'selection_key' => $selectionKey,
            'selected_lot_card_ids' => $selectedCards->pluck('id')->values()->all(),
            'selected_lot_cards' => $selectedCards
                ->map(fn (array $card) => [
                    'id' => $card['id'],
                    'title' => $card['title'],
                    'price' => $card['price'],
                ])
                ->values()
                ->all(),
            'selected_lot_cards_count' => $selectedCount,
            'selected_lot_cards_total' => $selectionTotal,
            'domestic_shipping_total' => $this->calculateDomesticShipping($selectedCount),
        ];
    }

    public function reserveForOrderItem(OrderItem $orderItem): void
    {
        if (! $this->isLotSelectionOrderItem($orderItem)) {
            return;
        }

        $listing = $this->lockListing($orderItem->listing);
        $lotData = $this->getLotData($listing);
        $cardIds = $this->selectionCardIdsFromMetadata($orderItem->metadata ?? []);
        $cards = collect(Arr::get($lotData, 'individual_cards', []))
            ->map(fn (mixed $card, int $index) => $this->normalizeCard($card, $index))
            ->map(function (?array $card) use ($cardIds, $orderItem) {
                if (! $card) {
                    return null;
                }

                if (! in_array($card['id'], $cardIds, true)) {
                    return $card;
                }

                if ($card['status'] !== 'available') {
                    throw ValidationException::withMessages([
                        'items' => [__('api.cart.lot_card_unavailable')],
                    ]);
                }

                $card['status'] = 'reserved';
                $card['reserved_order_id'] = $orderItem->order_id;
                $card['reserved_at'] = now()->toIso8601String();

                return $card;
            })
            ->filter()
            ->values()
            ->all();

        $lotData['individual_cards'] = $cards;
        $this->persistLotData($listing, $lotData);
        $this->syncListingAvailability($listing, $lotData);
    }

    public function finalizeOrderItem(OrderItem $orderItem): void
    {
        if (! $this->isLotSelectionOrderItem($orderItem)) {
            return;
        }

        $listing = $this->lockListing($orderItem->listing);
        $lotData = $this->getLotData($listing);
        $cardIds = $this->selectionCardIdsFromMetadata($orderItem->metadata ?? []);
        $cards = collect(Arr::get($lotData, 'individual_cards', []))
            ->map(fn (mixed $card, int $index) => $this->normalizeCard($card, $index))
            ->map(function (?array $card) use ($cardIds, $orderItem) {
                if (! $card) {
                    return null;
                }

                if (! in_array($card['id'], $cardIds, true)) {
                    return $card;
                }

                $card['status'] = 'sold';
                $card['sold_order_id'] = $orderItem->order_id;
                $card['sold_at'] = now()->toIso8601String();
                unset($card['reserved_order_id'], $card['reserved_at']);

                return $card;
            })
            ->filter()
            ->values()
            ->all();

        $lotData['individual_cards'] = $cards;
        $this->persistLotData($listing, $lotData);
        $this->syncListingAvailability($listing, $lotData);
    }

    public function releaseReservation(OrderItem $orderItem): void
    {
        if (! $this->isLotSelectionOrderItem($orderItem)) {
            return;
        }

        $listing = $this->lockListing($orderItem->listing);
        $lotData = $this->getLotData($listing);
        $cardIds = $this->selectionCardIdsFromMetadata($orderItem->metadata ?? []);
        $cards = collect(Arr::get($lotData, 'individual_cards', []))
            ->map(fn (mixed $card, int $index) => $this->normalizeCard($card, $index))
            ->map(function (?array $card) use ($cardIds, $orderItem) {
                if (! $card) {
                    return null;
                }

                if (
                    in_array($card['id'], $cardIds, true)
                    && $card['status'] === 'reserved'
                    && (int) ($card['reserved_order_id'] ?? 0) === (int) $orderItem->order_id
                ) {
                    $card['status'] = 'available';
                    unset($card['reserved_order_id'], $card['reserved_at']);
                }

                return $card;
            })
            ->filter()
            ->values()
            ->all();

        $lotData['individual_cards'] = $cards;
        $this->persistLotData($listing, $lotData);
        $this->syncListingAvailability($listing, $lotData);
    }

    public function calculateDomesticShipping(int $selectedCardsCount): float
    {
        $selectedCardsCount = max($selectedCardsCount, 0);

        if ($selectedCardsCount <= 10) {
            return 2.50;
        }

        return round(2.50 + (($selectedCardsCount - 10) * 0.25), 2);
    }

    public function hasValidSelection(Listing $listing, array $metadata): bool
    {
        try {
            $this->buildSelectionSnapshot($listing, $this->selectionCardIdsFromMetadata($metadata));

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    protected function isLotSelectionOrderItem(OrderItem $orderItem): bool
    {
        return Arr::get($orderItem->metadata ?? [], 'item_mode') === self::ITEM_MODE;
    }

    protected function selectionCardIdsFromMetadata(array $metadata): array
    {
        return Arr::get($metadata, 'selected_lot_card_ids', Arr::get($metadata, 'lot_card_ids', []));
    }

    protected function lockListing(?Listing $listing): Listing
    {
        return Listing::query()
            ->with('product')
            ->lockForUpdate()
            ->findOrFail($listing?->getKey());
    }

    protected function persistLotData(Listing $listing, array $lotData): void
    {
        $listing->forceFill([
            'lot_snapshot' => $lotData,
        ])->save();

        if ($listing->product) {
            $listing->product->forceFill([
                'lot_configuration' => $lotData,
            ])->save();
        }
    }

    protected function syncListingAvailability(Listing $listing, array $lotData): void
    {
        $availableCardsCount = collect(Arr::get($lotData, 'individual_cards', []))
            ->map(fn (mixed $card, int $index) => $this->normalizeCard($card, $index))
            ->filter(fn (?array $card) => $card && $card['status'] === 'available')
            ->count();

        if ($availableCardsCount <= 0) {
            $listing->forceFill([
                'available_quantity' => 0,
                'status' => 'sold',
                'availability' => 'sold_out',
            ])->save();

            return;
        }

        if (! in_array((string) $listing->status, ['active', 'published'], true)) {
            return;
        }

        $listing->forceFill([
            'availability' => 'available',
            'available_quantity' => max((int) ($listing->available_quantity ?? 1), 1),
        ])->save();
    }

    protected function getLotData(Listing $listing, mixed $product = null): array
    {
        $lotData = $listing->lot_snapshot
            ?? ($product?->lot_configuration ?? null)
            ?? Arr::get($listing->attributes ?? [], 'lot_summary');

        return is_array($lotData) ? $lotData : [];
    }

    protected function makeSelectionKey(array $ids): string
    {
        $ids = collect($ids)
            ->map(fn (mixed $value) => trim((string) $value))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return sha1(implode('|', $ids));
    }

    protected function normalizeCard(mixed $card, int $index): ?array
    {
        if (! is_array($card)) {
            return null;
        }

        $title = trim((string) Arr::get($card, 'title', ''));
        $price = round(max((float) Arr::get($card, 'price', 0), 0), 2);

        if ($title === '' || $price <= 0) {
            return null;
        }

        $id = trim((string) Arr::get($card, 'id', ''));
        if ($id === '') {
            $id = Str::slug(Str::limit($title, 42, ''), '-') . '-' . ($index + 1);
        }

        return [
            'id' => $id,
            'title' => $title,
            'price' => $price,
            'status' => in_array((string) Arr::get($card, 'status', 'available'), ['available', 'reserved', 'sold'], true)
                ? (string) Arr::get($card, 'status', 'available')
                : 'available',
            'reserved_order_id' => Arr::get($card, 'reserved_order_id'),
            'reserved_at' => Arr::get($card, 'reserved_at'),
            'sold_order_id' => Arr::get($card, 'sold_order_id'),
            'sold_at' => Arr::get($card, 'sold_at'),
        ];
    }
}
