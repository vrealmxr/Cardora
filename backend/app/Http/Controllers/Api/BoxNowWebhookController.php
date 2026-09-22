<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\BoxNowShipmentWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * BoxNow Webhook-Based Parcel Tracking (guide v1.4). Gives near-real-time status updates
 * instead of waiting for the next 30-minute poll (SyncBoxNowShipmentTracking). Polling keeps
 * running independently as a safety net, so this endpoint can fail closed on anything
 * suspicious without any loss of functionality — a rejected/misrouted webhook just means the
 * order catches up on the next poll instead of instantly.
 */
class BoxNowWebhookController extends Controller
{
    public function handle(Request $request, BoxNowShipmentWorkflowService $workflow): JsonResponse
    {
        $rawBody = $request->getContent();
        $payload = json_decode($rawBody, true);

        if (! is_array($payload)) {
            return response()->json(['message' => 'Invalid payload.'], 400);
        }

        if (! $this->verifySignature($rawBody, $payload)) {
            Log::warning('boxnow.webhook.signature_mismatch', ['type' => $payload['type'] ?? null]);

            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $parcelId = trim((string) ($data['parcelId'] ?? ''));
        $event = trim((string) ($data['event'] ?? ''));
        $eventAt = $this->parseTime($data['time'] ?? ($payload['time'] ?? null));

        if ($parcelId === '' || $event === '') {
            return response()->json(['message' => 'Missing parcelId/event, ignored.']);
        }

        $order = Order::query()
            ->where('shipping_carrier', 'boxnow')
            ->where(function ($query) use ($parcelId) {
                $query->where('shipment_tracking_number', $parcelId)
                    ->orWhere('tracking_number', $parcelId);
            })
            ->first();

        if (! $order) {
            // Acknowledge anyway — an unknown parcel isn't a delivery failure on our side, and
            // returning non-200 here would just make BoxNow retry it for 24h for nothing.
            Log::info('boxnow.webhook.unknown_parcel', ['parcelId' => $parcelId, 'event' => $event]);

            return response()->json(['message' => 'Order not found, acknowledged.']);
        }

        try {
            $workflow->applyWebhookEvent($order, $event, $eventAt, $payload);
        } catch (Throwable $exception) {
            Log::error('boxnow.webhook.apply_failed', [
                'order_id' => $order->getKey(),
                'event' => $event,
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Failed to process.'], 500);
        }

        return response()->json(['message' => 'ok']);
    }

    /**
     * datasignature = HMAC-SHA256(hex) of the raw "data" object exactly as received (BoxNow is
     * explicit: no reformatting/reordering before hashing), keyed with the Webhook Secret. Stage
     * and production each have their own secret, so accept either since one endpoint may
     * receive both while we're validating the integration.
     */
    protected function verifySignature(string $rawBody, array $payload): bool
    {
        $signature = trim((string) ($payload['datasignature'] ?? ''));

        if ($signature === '') {
            return false;
        }

        $rawData = $this->extractRawJsonObject($rawBody, 'data');

        if ($rawData === null) {
            return false;
        }

        $secrets = array_filter([
            config('services.boxnow.webhook_secret'),
            config('services.boxnow.test_webhook_secret'),
        ]);

        foreach ($secrets as $secret) {
            $expected = hash_hmac('sha256', $rawData, (string) $secret);

            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pull the exact raw substring for "<key>": { ... } out of the raw request body, preserving
     * original whitespace/key order — re-serializing the decoded PHP array would not reliably
     * reproduce the exact bytes BoxNow signed. Handles nested objects and escaped quotes inside
     * string values.
     */
    protected function extractRawJsonObject(string $rawBody, string $key): ?string
    {
        $needle = '"' . $key . '"';
        $keyPos = strpos($rawBody, $needle);

        if ($keyPos === false) {
            return null;
        }

        $colonPos = strpos($rawBody, ':', $keyPos + strlen($needle));

        if ($colonPos === false) {
            return null;
        }

        $length = strlen($rawBody);
        $pos = $colonPos + 1;

        while ($pos < $length && ctype_space($rawBody[$pos])) {
            $pos++;
        }

        if ($pos >= $length || $rawBody[$pos] !== '{') {
            return null;
        }

        $start = $pos;
        $depth = 0;
        $inString = false;
        $escaped = false;

        for ($i = $start; $i < $length; $i++) {
            $char = $rawBody[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;
            } elseif ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;

                if ($depth === 0) {
                    return substr($rawBody, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }

    protected function parseTime(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
