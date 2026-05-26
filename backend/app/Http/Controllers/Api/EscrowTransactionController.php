<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreEscrowTransactionRequest;
use App\Http\Resources\EscrowTransactionResource;
use App\Http\Controllers\Controller;
use App\Models\EscrowTransaction;
use Illuminate\Http\Request;

class EscrowTransactionController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->getKey();

        $transactions = EscrowTransaction::query()
            ->with(['order', 'buyer', 'seller', 'payout'])
            ->where(function ($query) use ($userId) {
                $query->where('buyer_id', $userId)->orWhere('seller_id', $userId);
            })
            ->when($request->filled('order_id'), fn ($query) => $query->where('order_id', $request->integer('order_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return EscrowTransactionResource::collection($transactions);
    }

    public function store(StoreEscrowTransactionRequest $request)
    {
        $transaction = EscrowTransaction::create($request->validated());

        return (new EscrowTransactionResource($transaction->load(['order', 'buyer', 'seller', 'payout'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(EscrowTransaction $escrowTransaction)
    {
        abort_unless(
            in_array(request()->user()->getKey(), [$escrowTransaction->buyer_id, $escrowTransaction->seller_id], true),
            403,
            __('api.errors.forbidden')
        );

        return new EscrowTransactionResource($escrowTransaction->load(['order', 'buyer', 'seller', 'payout']));
    }

    public function update(Request $request, EscrowTransaction $escrowTransaction)
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:50'],
            'provider_reference' => ['nullable', 'string', 'max:255'],
            'released_at' => ['nullable', 'date'],
            'disputed_at' => ['nullable', 'date'],
            'resolved_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ]);

        $escrowTransaction->update($validated);

        return new EscrowTransactionResource(
            $escrowTransaction->fresh()->load(['order', 'buyer', 'seller', 'payout'])
        );
    }

    public function destroy(EscrowTransaction $escrowTransaction)
    {
        $escrowTransaction->delete();

        return response()->noContent();
    }
}
