<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DiditService;
use App\Services\DiditCredentials;
use App\Services\DiditSignatureService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DiditController extends Controller
{
    public function show(Request $request, DiditService $didit)
    {
        abort_unless(config('didit.enabled'), 404);
        return response()->json(['data' => $didit->status($request->user())])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, DiditService $didit)
    {
        $request->validate(['consent' => ['required', 'accepted'], 'notice_version' => ['required', Rule::in([config('didit.notice_version')])]]);
        return response()->json(['data' => $didit->createSession($request->user(), app()->getLocale() === 'en' ? 'en' : 'el')], 201)->header('Cache-Control', 'no-store');
    }

    public function refresh(Request $request, DiditService $didit)
    {
        abort_unless(config('didit.enabled'), 404);
        $didit->reconcile($request->user());
        return $this->show($request, $didit);
    }

    public function webhook(Request $request, DiditSignatureService $signatures, DiditService $didit)
    {
        $event = json_decode($request->getContent(), true);
        abort_unless(is_array($event), 401, 'Invalid signature.');
        $scope = DiditCredentials::forWorkflow(is_string($event['workflow_id'] ?? null) ? $event['workflow_id'] : '');
        abort_unless(filled($scope['webhook_secret'] ?? null), 401, 'Invalid signature.');
        abort_unless($signatures->verify($request->getContent(), (string) $request->header('X-Signature-V2'), (string) $request->header('X-Timestamp'), (string) $scope['webhook_secret']), 401, 'Invalid signature.');
        return response()->json(['received' => true, 'outcome' => $didit->receive($event)]);
    }
}
