<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MarketplaceEventMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $recipient,
        public array $content
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject((string) ($this->content['subject'] ?? 'Cardora'))
            ->view('emails.marketplace-event');
    }
}
