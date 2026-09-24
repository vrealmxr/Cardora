<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BinderCard;
use App\Models\BinderCardPricePoint;
use App\Models\BinderGame;
use App\Models\BinderSet;
use App\Models\BinderUserCard;
use App\Models\BinderWatchedSet;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Product;
use App\Models\User;
use App\Services\UserNotificationPreferenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class BinderController extends Controller
{
    /**
     * Old binder_games.slug -> current slug, for games renamed after a
     * mis-scoped catalog registration (e.g. 'disney' turned out to always
     * mean Disney Lorcana specifically; 'star-wars' turned out to be Star
     * Wars Miniatures, not the current Star Wars: Unlimited game).
     * Renaming the row is correct, but an old bookmarked/shared URL like
     * /binder/star-wars must keep resolving instead of 404ing.
     */
    private const SLUG_ALIASES = [
        'disney' => 'disney-lorcana',
        'star-wars' => 'star-wars-miniatures',
    ];

    /**
     * Game picker screen: every game, grouped by category, with live set
     * and card counts.
     */
    public function games()
    {
        $games = BinderGame::query()
            ->withCount(['sets', 'cards'])
            ->orderBy('category')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (BinderGame $game) => [
                'slug' => $game->slug,
                'name' => $game->name,
                'category' => $game->category,
                'setsCount' => $game->sets_count,
                'cardsCount' => $game->cards_count,
            ])
            ->groupBy('category');

        return response()->json(['data' => $games]);
    }

    /**
     * Set catalog screen for one game: search + paginated set list.
     */
    public function sets(Request $request, string $gameSlug)
    {
        $gameSlug = self::SLUG_ALIASES[$gameSlug] ?? $gameSlug;
        $game = BinderGame::query()->where('slug', $gameSlug)->firstOrFail();

        $sets = BinderSet::query()
            ->where('game_id', $game->id)
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where('name', 'like', '%' . $request->string('search') . '%');
            })
            ->orderByDesc('released_at')
            ->paginate($request->integer('per_page', 24))
            ->through(fn (BinderSet $set) => [
                'id' => $set->id,
                'slug' => $set->slug,
                'name' => $set->name,
                'abbreviation' => $set->abbreviation,
                'releasedAt' => $set->released_at?->toDateString(),
                'cardCount' => $set->card_count,
            ]);

        return response()->json([
            'game' => ['slug' => $game->slug, 'name' => $game->name, 'category' => $game->category],
            'data' => $sets->items(),
            'meta' => [
                'currentPage' => $sets->currentPage(),
                'lastPage' => $sets->lastPage(),
                'total' => $sets->total(),
            ],
        ]);
    }

    /**
     * Card grid screen for one set: search + rarity filter + pagination,
     * annotated with the current user's ownership when authenticated.
     */
    public function cards(Request $request, int $setId)
    {
        $set = BinderSet::query()->with('game')->findOrFail($setId);
        // This route is public (guests can browse the catalog), so it isn't
        // behind auth:sanctum and $request->user() never resolves here even
        // with a valid token. Look the token up manually to annotate
        // ownership only when the visitor actually is signed in.
        $userId = $this->resolveOptionalUserId($request);

        // TCGplayer's catalog mixes actual cards with sealed products (booster
        // boxes, blister packs...) in the same set. Those have no number/
        // rarity, so they'd otherwise sort first and clutter the grid —
        // default to cards-only, matching the "Product Type" filter in the
        // reference app this flow is modeled on.
        $productType = $request->string('type', 'cards')->toString();

        $cards = BinderCard::query()
            ->where('set_id', $set->id)
            ->when($productType === 'cards', fn ($query) => $query->whereNotNull('number'))
            ->when($productType === 'sealed', fn ($query) => $query->whereNull('number'))
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where('name', 'like', '%' . $request->string('search') . '%');
            })
            ->when($request->filled('rarity'), function ($query) use ($request) {
                $query->where('rarity', $request->string('rarity'));
            })
            ->when($userId, function ($query) use ($userId) {
                $query->addSelect([
                    'ownedQuantity' => BinderUserCard::query()
                        ->select('quantity')
                        ->whereColumn('card_id', 'binder_cards.id')
                        ->where('user_id', $userId)
                        ->limit(1),
                    'ownedPrice' => BinderUserCard::query()
                        ->select('price')
                        ->whereColumn('card_id', 'binder_cards.id')
                        ->where('user_id', $userId)
                        ->limit(1),
                ]);
            })
            ->orderByRaw('CAST(SUBSTRING_INDEX(number, "/", 1) AS UNSIGNED), name')
            ->paginate($request->integer('per_page', 40))
            ->through(fn (BinderCard $card) => [
                'id' => $card->id,
                'name' => $card->name,
                'number' => $card->number,
                'rarity' => $card->rarity,
                'cardType' => $card->card_type,
                'imageUrl' => $card->image_url,
                'ownedQuantity' => $userId ? (int) ($card->ownedQuantity ?? 0) : null,
                'ownedPrice' => $userId && $card->ownedPrice !== null ? (float) $card->ownedPrice : null,
            ]);

        $rarities = BinderCard::query()
            ->where('set_id', $set->id)
            ->whereNotNull('rarity')
            ->distinct()
            ->orderBy('rarity')
            ->pluck('rarity');

        return response()->json([
            'set' => [
                'id' => $set->id,
                'name' => $set->name,
                'cardCount' => $set->card_count,
                'game' => ['slug' => $set->game->slug, 'name' => $set->game->name],
            ],
            'rarities' => $rarities,
            'data' => $cards->items(),
            'meta' => [
                'currentPage' => $cards->currentPage(),
                'lastPage' => $cards->lastPage(),
                'total' => $cards->total(),
            ],
        ]);
    }

    private function resolveOptionalUserId(Request $request): ?int
    {
        $token = $request->bearerToken();

        if (! $token) {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($token);

        return $accessToken?->tokenable?->getKey();
    }

    /**
     * Toggle ownership for a single card: no entry -> quantity 1 (+ the
     * price the user just entered), entry present -> removed entirely
     * (price goes with it). Matches the "tap a card to check it off, get
     * asked for a price" flow.
     */
    public function toggleOwned(Request $request, int $cardId)
    {
        $card = BinderCard::query()->findOrFail($cardId);
        $userId = $request->user()->getKey();

        $existing = BinderUserCard::query()
            ->where('user_id', $userId)
            ->where('card_id', $card->id)
            ->first();

        if ($existing) {
            $existing->delete();

            return response()->json(['data' => ['ownedQuantity' => 0, 'ownedPrice' => null]]);
        }

        $validated = $request->validate([
            'price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        $userCard = BinderUserCard::create([
            'user_id' => $userId,
            'card_id' => $card->id,
            'quantity' => 1,
            'price' => $validated['price'] ?? null,
        ]);

        return response()->json([
            'data' => ['ownedQuantity' => 1, 'ownedPrice' => $userCard->price !== null ? (float) $userCard->price : null],
        ]);
    }

    /**
     * Update the price on a card the user already owns — the "click the
     * price to edit it" interaction on an owned tile.
     */
    public function updatePrice(Request $request, int $cardId)
    {
        $userId = $request->user()->getKey();

        $validated = $request->validate([
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        $userCard = BinderUserCard::query()
            ->where('user_id', $userId)
            ->where('card_id', $cardId)
            ->firstOrFail();

        $userCard->update(['price' => $validated['price']]);

        return response()->json(['data' => ['ownedPrice' => (float) $userCard->price]]);
    }

    /**
     * Raise/lower how many copies of an owned card the user has — Cardora
     * PRO's "bulk list your duplicates" flow needs quantity > 1 to have
     * anything to list, so this stepper is a PRO feature.
     */
    public function updateQuantity(Request $request, int $cardId)
    {
        $user = $request->user();
        if (! $user->isProActive()) {
            return response()->json([
                'message' => 'Tracking multiple copies of a card is a Cardora PRO feature.',
                'upgrade_required' => true,
            ], 402);
        }

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $userCard = BinderUserCard::query()
            ->where('user_id', $user->getKey())
            ->where('card_id', $cardId)
            ->firstOrFail();

        $userCard->update(['quantity' => $validated['quantity']]);

        return response()->json(['data' => ['quantity' => $userCard->quantity]]);
    }

    /**
     * My binder / dashboard screen: every set the user owns at least one
     * card from, with completion + tracked value.
     */
    public function myCollection(Request $request)
    {
        $userId = $request->user()->getKey();

        $perSet = DB::table('binder_user_cards')
            ->join('binder_cards', 'binder_cards.id', '=', 'binder_user_cards.card_id')
            ->where('binder_user_cards.user_id', $userId)
            ->groupBy('binder_cards.set_id')
            ->select([
                'binder_cards.set_id',
                DB::raw('COUNT(*) as owned_count'),
                DB::raw('COALESCE(SUM(binder_user_cards.price), 0) as owned_value'),
            ])
            ->get()
            ->keyBy('set_id');

        $sets = BinderSet::query()
            ->with('game')
            ->whereIn('id', $perSet->keys())
            ->get()
            ->map(function (BinderSet $set) use ($perSet) {
                $row = $perSet->get($set->id);

                return [
                    'id' => $set->id,
                    'name' => $set->name,
                    'game' => ['slug' => $set->game->slug, 'name' => $set->game->name],
                    'ownedCount' => (int) $row->owned_count,
                    'cardCount' => $set->card_count,
                    'value' => round((float) $row->owned_value, 2),
                    'percent' => $set->card_count > 0 ? (int) round(($row->owned_count / $set->card_count) * 100) : 0,
                ];
            })
            ->sortByDesc('value')
            ->values();

        return response()->json(['data' => $sets]);
    }

    /**
     * Portfolio-wide stats for the dashboard: total tracked value, total
     * cards owned, value broken down by game, and the most valuable cards
     * in the collection.
     */
    public function portfolio(Request $request)
    {
        $user = $request->user();
        $userId = $user->getKey();
        $isPro = $user->isProActive();

        $totals = DB::table('binder_user_cards')
            ->where('user_id', $userId)
            ->selectRaw('COUNT(*) as total_owned, COALESCE(SUM(price), 0) as total_value')
            ->first();

        $setsRegistered = DB::table('binder_user_cards')
            ->join('binder_cards', 'binder_cards.id', '=', 'binder_user_cards.card_id')
            ->where('binder_user_cards.user_id', $userId)
            ->distinct('binder_cards.set_id')
            ->count('binder_cards.set_id');

        $byGame = DB::table('binder_user_cards')
            ->join('binder_cards', 'binder_cards.id', '=', 'binder_user_cards.card_id')
            ->join('binder_games', 'binder_games.id', '=', 'binder_cards.game_id')
            ->where('binder_user_cards.user_id', $userId)
            ->groupBy('binder_games.id', 'binder_games.name', 'binder_games.slug')
            ->select([
                'binder_games.slug',
                'binder_games.name',
                DB::raw('COUNT(*) as owned_count'),
                DB::raw('COALESCE(SUM(binder_user_cards.price), 0) as value'),
            ])
            ->orderByDesc('value')
            ->get()
            ->map(fn ($row) => [
                'slug' => $row->slug,
                'name' => $row->name,
                'ownedCount' => (int) $row->owned_count,
                'value' => round((float) $row->value, 2),
            ]);

        $topCards = DB::table('binder_user_cards')
            ->join('binder_cards', 'binder_cards.id', '=', 'binder_user_cards.card_id')
            ->join('binder_sets', 'binder_sets.id', '=', 'binder_cards.set_id')
            ->where('binder_user_cards.user_id', $userId)
            ->whereNotNull('binder_user_cards.price')
            ->orderByDesc('binder_user_cards.price')
            ->limit(5)
            ->select([
                'binder_cards.id',
                'binder_cards.name',
                'binder_cards.image_url',
                'binder_sets.name as set_name',
                'binder_user_cards.price',
            ])
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'imageUrl' => $row->image_url,
                'setName' => $row->set_name,
                'price' => (float) $row->price,
            ]);

        $proAnalytics = [];
        if ($isPro) {
            $proAnalytics = [
                'pro' => true,
                'unrealizedProfitLoss' => round($this->calculateUnrealizedProfitLoss($userId), 2),
                'byRarity' => $this->rarityBreakdown($userId),
            ];
        } else {
            $proAnalytics = ['pro' => false];
        }

        return response()->json([
            'data' => array_merge([
                'totalValue' => round((float) $totals->total_value, 2),
                'totalOwned' => (int) $totals->total_owned,
                'setsRegistered' => $setsRegistered,
                'byGame' => $byGame,
                'topCards' => $topCards,
            ], $proAnalytics),
        ]);
    }

    /**
     * Cardora PRO: unrealized profit/loss across every owned card that has
     * both a recorded purchase price and at least one real Cardora price
     * point. Cards with no market activity yet are simply excluded rather
     * than guessed at.
     */
    private function calculateUnrealizedProfitLoss(int $userId): float
    {
        $purchasePrices = DB::table('binder_user_cards')
            ->where('user_id', $userId)
            ->whereNotNull('price')
            ->pluck('price', 'card_id');

        if ($purchasePrices->isEmpty()) {
            return 0.0;
        }

        $latestPrices = DB::table('binder_card_price_points')
            ->whereIn('binder_card_id', $purchasePrices->keys())
            ->orderByDesc('recorded_at')
            ->get(['binder_card_id', 'price'])
            ->unique('binder_card_id')
            ->pluck('price', 'binder_card_id');

        $total = 0.0;
        foreach ($purchasePrices as $cardId => $purchasePrice) {
            if (isset($latestPrices[$cardId])) {
                $total += (float) $latestPrices[$cardId] - (float) $purchasePrice;
            }
        }

        return $total;
    }

    /** Cardora PRO: owned-value breakdown by rarity. */
    private function rarityBreakdown(int $userId)
    {
        return DB::table('binder_user_cards')
            ->join('binder_cards', 'binder_cards.id', '=', 'binder_user_cards.card_id')
            ->where('binder_user_cards.user_id', $userId)
            ->whereNotNull('binder_cards.rarity')
            ->groupBy('binder_cards.rarity')
            ->select([
                'binder_cards.rarity',
                DB::raw('COUNT(*) as owned_count'),
                DB::raw('COALESCE(SUM(binder_user_cards.price), 0) as value'),
            ])
            ->orderByDesc('value')
            ->get()
            ->map(fn ($row) => [
                'rarity' => $row->rarity,
                'ownedCount' => (int) $row->owned_count,
                'value' => round((float) $row->value, 2),
            ]);
    }

    /**
     * Alerts settings screen: the global on/off toggle plus every set that
     * currently qualifies for "someone listed a card from this set" pings —
     * sets the user owns at least one card from (automatic) and sets they
     * explicitly chose to watch (opt-in, no ownership required).
     */
    public function alertSettings(Request $request, UserNotificationPreferenceService $preferences)
    {
        $user = $request->user();
        $userId = $user->getKey();

        $ownedSetIds = DB::table('binder_user_cards')
            ->join('binder_cards', 'binder_cards.id', '=', 'binder_user_cards.card_id')
            ->where('binder_user_cards.user_id', $userId)
            ->distinct()
            ->pluck('binder_cards.set_id');

        $watchedSetIds = BinderWatchedSet::query()
            ->where('user_id', $userId)
            ->pluck('set_id');

        $ownedSetIdSet = $ownedSetIds->map(fn ($id) => (int) $id)->flip();

        $allSetIds = $ownedSetIds->merge($watchedSetIds)->map(fn ($id) => (int) $id)->unique()->values();

        $sets = BinderSet::query()
            ->with('game')
            ->whereIn('id', $allSetIds)
            ->get()
            ->map(fn (BinderSet $set) => [
                'id' => $set->id,
                'name' => $set->name,
                'cardCount' => $set->card_count,
                'game' => ['slug' => $set->game->slug, 'name' => $set->game->name],
                'source' => $ownedSetIdSet->has($set->id) ? 'owned' : 'watched',
            ])
            ->sortBy('name')
            ->values();

        return response()->json([
            'data' => [
                'enabled' => $preferences->allowsInApp($userId, 'binder_alerts'),
                'sets' => $sets,
                'isPro' => $user->isProActive(),
                'watchedCount' => $watchedSetIds->count(),
                'watchedLimit' => $user->isProActive() ? null : self::FREE_WATCHED_SET_LIMIT,
            ],
        ]);
    }

    /**
     * Flip the global "notify me about listings from sets I own or watch"
     * toggle. Kept as a single on/off switch here for simplicity — the full
     * in-app/email split for power users still lives on the main
     * notification preferences screen (same underlying category).
     */
    public function updateAlertSettings(Request $request, UserNotificationPreferenceService $preferences)
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $current = $preferences->normalize($user->notification_preferences);
        $current['binder_alerts'] = [
            'in_app' => $validated['enabled'],
            'email' => $validated['enabled'],
        ];

        $user->update(['notification_preferences' => $preferences->normalize($current)]);

        return response()->json(['data' => ['enabled' => $validated['enabled']]]);
    }

    /**
     * Start watching a set for listing alerts without owning any of its
     * cards yet — "which set would you like to start building".
     */
    private const FREE_WATCHED_SET_LIMIT = 10;

    public function watchSet(Request $request)
    {
        $validated = $request->validate([
            'set_id' => ['required', 'integer', 'exists:binder_sets,id'],
        ]);

        $user = $request->user();
        $userId = $user->getKey();

        $alreadyWatching = BinderWatchedSet::query()
            ->where('user_id', $userId)
            ->where('set_id', $validated['set_id'])
            ->exists();

        if (! $alreadyWatching && ! $user->isProActive()) {
            $watchedCount = BinderWatchedSet::query()->where('user_id', $userId)->count();

            if ($watchedCount >= self::FREE_WATCHED_SET_LIMIT) {
                return response()->json([
                    'message' => sprintf(
                        'Free accounts can watch up to %d sets. Upgrade to Cardora PRO for unlimited missing-card alerts.',
                        self::FREE_WATCHED_SET_LIMIT
                    ),
                    'upgrade_required' => true,
                    'limit' => self::FREE_WATCHED_SET_LIMIT,
                ], 402);
            }
        }

        BinderWatchedSet::query()->firstOrCreate([
            'user_id' => $userId,
            'set_id' => $validated['set_id'],
        ]);

        return response()->json(['data' => ['watching' => true]], 201);
    }

    /**
     * Stop explicitly watching a set. Sets the user owns cards from keep
     * generating alerts regardless — this only removes the opt-in watch.
     */
    public function unwatchSet(Request $request, int $setId)
    {
        BinderWatchedSet::query()
            ->where('user_id', $request->user()->getKey())
            ->where('set_id', $setId)
            ->delete();

        return response()->json(['data' => ['watching' => false]]);
    }

    /**
     * Price history for one card. FREE sees only the latest point; PRO sees
     * the full timeline (real Cardora listing/sale activity, not mock).
     */
    public function priceHistory(Request $request, int $cardId)
    {
        $card = BinderCard::query()->findOrFail($cardId);
        $userId = $this->resolveOptionalUserId($request);
        $proStatus = $userId ? User::query()->whereKey($userId)->value('pro_status') : null;
        $isPro = in_array($proStatus, ['trialing', 'active'], true);

        $query = BinderCardPricePoint::query()
            ->where('binder_card_id', $card->id)
            ->orderByDesc('recorded_at');

        $points = $isPro ? $query->get() : $query->limit(1)->get();

        return response()->json([
            'data' => [
                'cardId' => $card->id,
                'pro' => $isPro,
                'points' => $points->map(fn (BinderCardPricePoint $point) => [
                    'price' => (float) $point->price,
                    'source' => $point->source,
                    'recordedAt' => $point->recorded_at,
                ])->values(),
            ],
        ]);
    }

    /**
     * Cardora PRO: export the full owned collection as CSV.
     */
    public function export(Request $request)
    {
        $user = $request->user();
        if (! $user->isProActive()) {
            return response()->json([
                'message' => 'Exporting your collection is a Cardora PRO feature.',
                'upgrade_required' => true,
            ], 402);
        }

        $rows = DB::table('binder_user_cards')
            ->join('binder_cards', 'binder_cards.id', '=', 'binder_user_cards.card_id')
            ->join('binder_sets', 'binder_sets.id', '=', 'binder_cards.set_id')
            ->join('binder_games', 'binder_games.id', '=', 'binder_cards.game_id')
            ->where('binder_user_cards.user_id', $user->getKey())
            ->orderBy('binder_games.name')
            ->orderBy('binder_sets.name')
            ->select([
                'binder_games.name as game',
                'binder_sets.name as set',
                'binder_cards.name as card',
                'binder_cards.number',
                'binder_cards.rarity',
                'binder_user_cards.quantity',
                'binder_user_cards.price as purchase_price',
            ])
            ->get();

        $csv = "Game,Set,Card,Number,Rarity,Quantity,Purchase Price\n";
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(
                fn ($value) => '"'.str_replace('"', '""', (string) ($value ?? '')).'"',
                [$row->game, $row->set, $row->card, $row->number, $row->rarity, $row->quantity, $row->purchase_price]
            ))."\n";
        }

        // Returned as JSON (not a raw file download) so the frontend's
        // JSON-only API client can fetch it like any other endpoint — the
        // browser builds the actual downloadable file client-side.
        return response()->json([
            'data' => [
                'csv' => $csv,
                'filename' => 'cardora-binder-export.csv',
            ],
        ]);
    }

    /**
     * Owned cards with more than one copy — the pool "bulk list your
     * duplicates" (Cardora PRO) draws from.
     */
    public function duplicates(Request $request)
    {
        $user = $request->user();
        if (! $user->isProActive()) {
            return response()->json([
                'message' => 'Bulk-listing duplicates is a Cardora PRO feature.',
                'upgrade_required' => true,
            ], 402);
        }

        $cards = BinderUserCard::query()
            ->with(['card.set.game'])
            ->where('user_id', $user->getKey())
            ->where('quantity', '>', 1)
            ->get()
            ->map(fn (BinderUserCard $userCard) => [
                'cardId' => $userCard->card_id,
                'name' => $userCard->card->name,
                'number' => $userCard->card->number,
                'imageUrl' => $userCard->card->image_url,
                'setName' => $userCard->card->set->name,
                'gameSlug' => $userCard->card->set->game->slug,
                'quantity' => $userCard->quantity,
                'purchasePrice' => $userCard->price !== null ? (float) $userCard->price : null,
            ]);

        return response()->json(['data' => $cards]);
    }

    /**
     * Cardora PRO: bulk-create draft listings for a batch of owned
     * duplicates in one go, pre-filled and tagged with their matched
     * binder_card_id so they immediately participate in price history and
     * Binder alerts once published.
     */
    public function bulkList(Request $request)
    {
        $user = $request->user();
        if (! $user->isProActive()) {
            return response()->json([
                'message' => 'Bulk-listing duplicates is a Cardora PRO feature.',
                'upgrade_required' => true,
            ], 402);
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.card_id' => ['required', 'integer', 'exists:binder_cards,id'],
            'items.*.price' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'items.*.condition' => ['nullable', 'string', 'max:255'],
        ]);

        $category = Category::query()
            ->where('slug', 'kartes')
            ->orWhere('slug', 'cards')
            ->first();

        if (! $category) {
            return response()->json(['message' => 'The Cards category is not configured.'], 500);
        }

        $created = [];

        foreach ($validated['items'] as $item) {
            $owned = BinderUserCard::query()
                ->where('user_id', $user->getKey())
                ->where('card_id', $item['card_id'])
                ->where('quantity', '>', 1)
                ->with('card.set')
                ->first();

            if (! $owned) {
                continue;
            }

            $card = $owned->card;
            $title = trim(sprintf('%s %s (%s)', $card->name, $card->number ?? '', $card->set->name ?? ''));

            $product = Product::create([
                'category_id' => $category->getKey(),
                'binder_card_id' => $card->getKey(),
                'title' => $title,
                'slug' => $this->uniqueProductSlug($title),
                'set_name' => $card->set->name ?? null,
                'item_number' => $card->number,
                'description' => '',
            ]);

            $listing = Listing::create([
                'product_id' => $product->getKey(),
                'category_id' => $category->getKey(),
                'seller_id' => $user->getKey(),
                'title_snapshot' => $title,
                'price' => $item['price'],
                'quantity' => 1,
                'available_quantity' => 1,
                'condition' => $item['condition'] ?? null,
                'status' => 'pending_review',
                'sale_format' => 'fixed_price',
            ]);

            $created[] = $listing->getKey();
        }

        return response()->json(['data' => ['createdListingIds' => $created]], 201);
    }

    private function uniqueProductSlug(string $title): string
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
}
