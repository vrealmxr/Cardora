<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CardoraResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(protected string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $isEnglish = ($notifiable->locale ?? 'el') === 'en';
        $resetUrl = sprintf(
            '%s?token=%s&email=%s',
            rtrim((string) config('services.frontend.password_reset_url'), '/'),
            urlencode($this->token),
            urlencode((string) $notifiable->email),
        );

        $content = $isEnglish
            ? [
                'subject' => 'Reset your Cardora password',
                'eyebrow' => 'Account security',
                'title' => 'Password reset request',
                'body' => 'We received a request to reset your Cardora password. Use the button below to set a new password.',
                'cta' => 'Set a new password',
                'url' => $resetUrl,
                'footer' => 'If you did not request this reset, you can ignore this email.',
            ]
            : [
                'subject' => 'Επαναφορά κωδικού Cardora',
                'eyebrow' => 'Ασφάλεια λογαριασμού',
                'title' => 'Αίτημα επαναφοράς κωδικού',
                'body' => 'Λάβαμε αίτημα για επαναφορά του κωδικού σου στην Cardora. Πάτησε το κουμπί παρακάτω για να ορίσεις νέο κωδικό.',
                'cta' => 'Ορισμός νέου κωδικού',
                'url' => $resetUrl,
                'footer' => 'Αν δεν ζήτησες εσύ επαναφορά κωδικού, μπορείς να αγνοήσεις αυτό το email.',
            ];

        return (new MailMessage())
            ->subject($content['subject'])
            ->view('emails.marketplace-event', [
                'recipient' => $notifiable,
                'content' => $content,
            ]);
    }
}
