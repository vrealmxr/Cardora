<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVerificationSubmissionRequest;
use App\Http\Requests\UpdateVerificationSubmissionRequest;
use App\Http\Resources\VerificationSubmissionResource;
use App\Mail\MarketplaceEventMail;
use App\Models\VerificationSubmission;
use App\Services\MarketplaceNotificationService;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function show(Request $request)
    {
        $submissions = VerificationSubmission::query()
            ->with(['documents', 'user', 'reviewer'])
            ->where('user_id', $request->user()->getKey())
            ->latest()
            ->paginate($request->integer('per_page', 10));

        return VerificationSubmissionResource::collection($submissions);
    }

    public function store(
        StoreVerificationSubmissionRequest $request,
        MarketplaceNotificationService $notifications
    ) {
        $validated = $request->validated();
        $documents = $validated['documents'] ?? [];
        unset($validated['documents']);

        $submission = VerificationSubmission::create([
            ...$validated,
            'user_id' => $request->user()->getKey(),
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        if ($documents !== []) {
            $submission->documents()->createMany($documents);
        }

        $notifications->createForUser(
            $request->user()->getKey(),
            'verification_submitted',
            __('api.notifications.verification_submitted_title'),
            __('api.notifications.verification_submitted_body'),
            ['verification_submission_id' => $submission->getKey()],
            'security'
        );

        $notifications->sendEmailIfAllowed(
            $request->user(),
            new MarketplaceEventMail(
                $request->user(),
                $this->verificationMailContent(
                    $request->user()->locale,
                    (string) $submission->verification_type,
                    'submitted'
                )
            ),
            'security'
        );

        return response()->json([
            'message' => __('api.verification.submitted'),
            'data' => new VerificationSubmissionResource(
                $submission->load(['documents', 'user', 'reviewer'])
            ),
        ], 201);
    }

    public function update(
        UpdateVerificationSubmissionRequest $request,
        VerificationSubmission $verificationSubmission,
        MarketplaceNotificationService $notifications
    ) {
        abort_unless(
            $verificationSubmission->user_id === $request->user()->getKey(),
            403,
            __('api.errors.forbidden')
        );

        $validated = $request->validated();
        $documents = $validated['documents'] ?? null;
        unset($validated['documents']);

        // New documents mean the submission needs a fresh look — without
        // this, adding files to an already-approved/rejected submission
        // silently left it "Verified"/"Rejected" instead of re-entering the
        // moderation queue (status isn't user-settable via this request on
        // purpose, so nothing else resets it).
        if (is_array($documents) && $documents !== []) {
            $validated['status'] = 'submitted';
            $validated['reviewed_at'] = null;
            $validated['reviewed_by'] = null;
        }

        $verificationSubmission->update($validated);

        if (is_array($documents) && $documents !== []) {
            $verificationSubmission->documents()->createMany($documents);
        }

        $notifications->sendEmailIfAllowed(
            $request->user(),
            new MarketplaceEventMail(
                $request->user(),
                $this->verificationMailContent(
                    $request->user()->locale,
                    (string) $verificationSubmission->verification_type,
                    (string) ($validated['status'] ?? $verificationSubmission->status ?? 'updated')
                )
            ),
            'security'
        );

        return response()->json([
            'message' => __('api.verification.updated'),
            'data' => new VerificationSubmissionResource(
                $verificationSubmission->fresh()->load(['documents', 'user', 'reviewer'])
            ),
        ]);
    }

    protected function verificationMailContent(?string $locale, string $type, string $status): array
    {
        $isEnglish = $locale === 'en';
        $frontendUrl = rtrim((string) config('services.frontend.url', config('app.url', 'http://localhost:5173')), '/');
        $normalizedType = $type !== '' ? $type : 'verification';
        $normalizedStatus = $status !== '' ? $status : 'updated';

        if ($isEnglish) {
            return [
                'subject' => 'Verification update on Cardora',
                'eyebrow' => 'Account security',
                'title' => 'Your verification request was updated',
                'body' => 'There is a new verification update in your Cardora account.',
                'details' => [
                    ['label' => 'Type', 'value' => $normalizedType],
                    ['label' => 'Status', 'value' => $normalizedStatus],
                ],
                'cta' => 'Open verification center',
                'url' => $frontendUrl.'/epalithefsi-logariasmou',
                'footer' => 'Security notifications are kept active to protect account access and payout readiness.',
            ];
        }

        return [
            'subject' => 'Ενημέρωση επαλήθευσης στην Cardora',
            'eyebrow' => 'Ασφάλεια λογαριασμού',
            'title' => 'Υπάρχει νέα ενημέρωση στο verification σου',
            'body' => 'Υπάρχει νέα ενημέρωση επαλήθευσης στον λογαριασμό σου στην Cardora.',
            'details' => [
                ['label' => 'Τύπος', 'value' => $normalizedType],
                ['label' => 'Κατάσταση', 'value' => $normalizedStatus],
            ],
            'cta' => 'Άνοιγμα verification center',
            'url' => $frontendUrl.'/epalithefsi-logariasmou',
            'footer' => 'Οι ειδοποιήσεις ασφάλειας παραμένουν ενεργές για προστασία πρόσβασης και payout readiness.',
        ];
    }
}
