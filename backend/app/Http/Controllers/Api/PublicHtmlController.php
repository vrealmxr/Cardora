<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PublicOriginRenderer;
use Illuminate\Http\Request;

class PublicHtmlController extends Controller
{
    public function __invoke(Request $request, PublicOriginRenderer $renderer, string $digest)
    {
        $input = $renderer->envelope($request, $digest);
        try {
            $result = $renderer->render($renderer->data($input));
        } catch (\Throwable $error) {
            // No private data or stack trace is returned by this public gateway.
            logger()->warning('Public origin renderer unavailable', ['type' => get_class($error)]);
            return response('Public rendering temporarily unavailable', 503, ['Cache-Control'=>'no-store', 'Retry-After'=>'30']);
        }
        // Private transport, not the final document response. Preserve exact HTML
        // through the API proxy; Pages restores the validated document MIME type.
        $headers = ['Cache-Control'=>'no-store', 'X-Content-Type-Options'=>'nosniff',
            'Content-Type'=>'application/octet-stream',
            'X-Cardora-Document-Type'=>$result['headers']['content-type'] ?? 'text/html; charset=utf-8'];
        if (isset($result['headers']['x-robots-tag'])) $headers['X-Robots-Tag'] = $result['headers']['x-robots-tag'];
        return response($result['body'], $result['status'], $headers);
    }
}
