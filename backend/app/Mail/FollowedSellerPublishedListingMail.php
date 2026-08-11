<?php

namespace App\Mail;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FollowedSellerPublishedListingMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $recipient,
        public User $seller,
        public Listing $listing,
        public string $listingUrl,
        public array $copy
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject($this->copy['subject'])
            ->view('emails.followed-seller-published-listing');
    }
}
