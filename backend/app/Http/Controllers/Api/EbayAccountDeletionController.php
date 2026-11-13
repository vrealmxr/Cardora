<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * eBay Marketplace Account Deletion/Closure notifications.
 *
 * eBay requires every production keyset to subscribe to these (or apply for
 * an exemption). Cardora only reads public listings with an application
 * token and stores no eBay user data, so there is nothing to delete: we
 * answer the validation challenge and acknowledge each notification.
 *
 * https://developer.ebay.com/marketplace-account-deletion
 */
class EbayAccountDeletionController extends Controller
{
    /**
     * GET ?challenge_code=... — endpoint validation.
     * challengeResponse = hex(sha256(challengeCode + verificationToken + endpointUrl))
     */
    public function challenge(Request $request)
    {
        $challengeCode = (string) $request->query('challenge_code', '');
        $token = (string) config('services.ebay.deletion_verification_token');
        $endpoint = (string) config('services.ebay.deletion_endpoint_url');

        if ($challengeCode === '' || $token === '') {
            return response()->json(['message' => 'Missing challenge_code or verification token.'], 400);
        }

        return response()->json([
            'challengeResponse' => hash('sha256', $challengeCode.$token.$endpoint),
        ]);
    }

    /**
     * POST — a user deleted their eBay account. We hold no eBay user data;
     * log the notification id and acknowledge so eBay stops retrying.
     */
    public function notify(Request $request)
    {
        Log::info('eBay account deletion notification received', [
            'notification_id' => data_get($request->all(), 'notification.notificationId'),
            'topic' => data_get($request->all(), 'metadata.topic'),
        ]);

        return response()->noContent(204);
    }
}
