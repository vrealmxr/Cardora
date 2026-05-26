<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCollectionEntryRequest;
use App\Http\Requests\UpdateCollectionEntryRequest;
use App\Http\Resources\CollectionEntryResource;
use App\Models\CollectionEntry;
use Illuminate\Http\Request;

class ProfileCollectionController extends Controller
{
    public function index(Request $request)
    {
        $entries = CollectionEntry::query()
            ->with(['product.category'])
            ->where('user_id', $request->user()->getKey())
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->latest('id')
            ->get();

        return CollectionEntryResource::collection($entries);
    }

    public function store(StoreCollectionEntryRequest $request)
    {
        $entry = CollectionEntry::create([
            ...$request->validated(),
            'user_id' => $request->user()->getKey(),
        ]);

        return response()->json([
            'message' => __('api.profile.collection_created'),
            'data' => new CollectionEntryResource($entry->load(['product.category'])),
        ], 201);
    }

    public function update(UpdateCollectionEntryRequest $request, CollectionEntry $collectionEntry)
    {
        $this->ensureOwnership($request, $collectionEntry);

        $collectionEntry->update($request->validated());

        return response()->json([
            'message' => __('api.profile.collection_updated'),
            'data' => new CollectionEntryResource($collectionEntry->fresh()->load(['product.category'])),
        ]);
    }

    public function destroy(Request $request, CollectionEntry $collectionEntry)
    {
        $this->ensureOwnership($request, $collectionEntry);

        $collectionEntry->delete();

        return response()->json([
            'message' => __('api.profile.collection_deleted'),
        ]);
    }

    protected function ensureOwnership(Request $request, CollectionEntry $collectionEntry): void
    {
        abort_unless(
            $collectionEntry->user_id === $request->user()->getKey(),
            403,
            __('api.errors.forbidden')
        );
    }
}
