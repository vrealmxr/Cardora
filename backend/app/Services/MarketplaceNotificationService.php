<?php

namespace App\Services;

use App\Models\User;
use App\Models\Notification;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MarketplaceNotificationService
{
    public function __construct(
        protected UserNotificationPreferenceService $preferences,
        protected ApnsPushNotificationService $push
    ) {
    }

    public function createForUser(
        int $userId,
        string $type,
        string $title,
        ?string $body = null,
        array $data = [],
        ?string $preferenceCategory = null
    ): ?Notification {
        if ($preferenceCategory && ! $this->preferences->allowsInApp($userId, $preferenceCategory)) {
            $this->push->sendIfAllowed($userId, $type, $title, $body, $data, $preferenceCategory);

            return null;
        }

        $notification = Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data !== [] ? $data : null,
        ]);

        $this->push->sendIfAllowed($userId, $type, $title, $body, $data, $preferenceCategory);

        return $notification;
    }

    public function createForUsers(
        array $userIds,
        string $type,
        string $title,
        ?string $body = null,
        array $data = [],
        ?string $preferenceCategory = null
    ): void {
        foreach (array_unique($userIds) as $userId) {
            $this->createForUser((int) $userId, $type, $title, $body, $data, $preferenceCategory);
        }
    }

    public function sendEmailIfAllowed(User $recipient, Mailable $mailable, ?string $preferenceCategory = null): bool
    {
        if (! filter_var($recipient->email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if ($preferenceCategory && ! $this->preferences->allowsEmail($recipient, $preferenceCategory)) {
            return false;
        }

        try {
            Mail::to($recipient->email)->send($mailable);
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }

        return true;
    }
}
