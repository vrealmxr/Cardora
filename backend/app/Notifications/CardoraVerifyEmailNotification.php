<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class CardoraVerifyEmailNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $isEnglish = ($notifiable->locale ?? 'el') === 'en';
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes((int) config('services.frontend.email_verification_expire_minutes', 1440)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );

        $content = $isEnglish
            ? [
                'subject' => 'Confirm your Cardora email',
                'eyebrow' => 'Account security',
                'title' => 'Confirm your email address',
                'body' => 'Verify your email to keep your Cardora account protected and to unlock full marketplace access.',
                'details' => [
                    ['label' => 'Account email', 'value' => (string) $notifiable->getEmailForVerification()],
                ],
                'cta' => 'Confirm email',
                'url' => $verificationUrl,
                'footer' => 'If you did not create a Cardora account, you can safely ignore this email.',
            ]
            : [
                'subject' => 'Επιβεβαίωση email Cardora',
                'eyebrow' => 'Ασφάλεια λογαριασμού',
                'title' => 'Επιβεβαίωσε τη διεύθυνση email σου',
                'body' => 'Επιβεβαίωσε το email σου για να παραμείνει προστατευμένος ο λογαριασμός Cardora και να έχεις πλήρη πρόσβαση στο marketplace.',
                'details' => [
                    ['label' => 'Email λογαριασμού', 'value' => (string) $notifiable->getEmailForVerification()],
                ],
                'cta' => 'Επιβεβαίωση email',
                'url' => $verificationUrl,
                'footer' => 'Αν δεν δημιούργησες εσύ λογαριασμό Cardora, μπορείς να αγνοήσεις αυτό το μήνυμα.',
            ];

        return (new MailMessage())
            ->subject($content['subject'])
            ->view('emails.marketplace-event', [
                'recipient' => $notifiable,
                'content' => $content,
            ]);
    }
}
