<?php

namespace App\Services;

use App\Models\SellerBalance;
use App\Models\SellerPayoutAccount;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient;

class StripeConnectService
{
    protected ?StripeClient $client = null;

    public function __construct()
    {
    }

    public function createExpressAccount(User $seller): SellerPayoutAccount
    {
        $existingAccount = $seller->sellerPayoutAccount;

        if ($existingAccount?->stripe_account_id) {
            return $this->syncLocalAccount($existingAccount);
        }

        try {
            $account = $this->stripe()->accounts->create([
                'type' => 'express',
                'country' => config('services.stripe.connect_country', 'GR'),
                'email' => $seller->email,
                'business_type' => 'individual',
                'capabilities' => [
                    'card_payments' => ['requested' => true],
                    'transfers' => ['requested' => true],
                ],
                'metadata' => [
                    'seller_id' => (string) $seller->getKey(),
                    'handle' => (string) ($seller->handle ?? ''),
                ],
            ]);
        } catch (InvalidRequestException $exception) {
            Log::error('Stripe Connect account creation failed.', [
                'userId' => $seller->getKey(),
                'error' => $exception->getMessage(),
            ]);

            $message = $exception->getMessage();

            if (str_contains($message, "signed up for Connect")) {
                throw ValidationException::withMessages([
                    'stripe' => [
                        'Το Stripe account της πλατφόρμας δεν έχει ακόμη ενεργοποιημένο Connect στο dashboard. Άνοιξε το Stripe Dashboard στο test mode, πήγαινε στο Connect και ολοκλήρωσε το setup του platform account. Μετά ξαναπάτησε «Δημιουργία Stripe Connected Account».',
                    ],
                ]);
            }

            throw ValidationException::withMessages([
                'stripe' => [$message],
            ]);
        }

        $localAccount = SellerPayoutAccount::create([
            'seller_id' => $seller->getKey(),
            'stripe_account_id' => $account->id,
            'account_type' => 'express',
            'country' => $account->country,
            'default_currency' => strtoupper((string) ($account->default_currency ?? 'EUR')),
            'onboarding_completed' => false,
            'charges_enabled' => (bool) $account->charges_enabled,
            'payouts_enabled' => (bool) $account->payouts_enabled,
            'details_submitted' => (bool) $account->details_submitted,
            'raw_requirements' => $account->requirements?->toArray() ?? null,
        ]);

        SellerBalance::firstOrCreate(
            [
                'seller_id' => $seller->getKey(),
                'currency' => strtoupper((string) ($account->default_currency ?? 'EUR')),
            ],
            [
                'pending_amount' => 0,
                'available_amount' => 0,
                'paid_out_amount' => 0,
            ]
        );

        return $this->syncLocalAccount($localAccount, $account);
    }

    public function createAccountLink(string $stripeAccountId, ?SellerPayoutAccount $localAccount = null): array
    {
        $localAccount ??= SellerPayoutAccount::query()
            ->where('stripe_account_id', $stripeAccountId)
            ->firstOrFail();

        $refreshUrl = URL::temporarySignedRoute(
            'stripe.connect.refresh',
            now()->addHours(6),
            ['account' => $localAccount->getKey()]
        );

        $returnUrl = URL::temporarySignedRoute(
            'stripe.connect.success',
            now()->addHours(6),
            ['account' => $localAccount->getKey()]
        );

        $accountLink = $this->stripe()->accountLinks->create([
            'account' => $stripeAccountId,
            'refresh_url' => $refreshUrl,
            'return_url' => $returnUrl,
            'type' => 'account_onboarding',
        ]);

        return [
            'url' => $accountLink->url,
            'expires_at' => $accountLink->expires_at,
        ];
    }

    public function retrieveAccount(string $stripeAccountId): object
    {
        return $this->stripe()->accounts->retrieve($stripeAccountId, []);
    }

    public function createDashboardLoginLink(string $stripeAccountId): string
    {
        $loginLink = $this->stripe()->accounts->createLoginLink($stripeAccountId);

        return (string) $loginLink->url;
    }

    public function syncLocalAccount(SellerPayoutAccount $localAccount, ?object $stripeAccount = null): SellerPayoutAccount
    {
        $stripeAccount ??= $this->retrieveAccount($localAccount->stripe_account_id);

        $localAccount->update([
            'country' => $stripeAccount->country ?: $localAccount->country,
            'default_currency' => strtoupper((string) ($stripeAccount->default_currency ?? $localAccount->default_currency ?? 'EUR')),
            'charges_enabled' => (bool) $stripeAccount->charges_enabled,
            'payouts_enabled' => (bool) $stripeAccount->payouts_enabled,
            'details_submitted' => (bool) $stripeAccount->details_submitted,
            'onboarding_completed' => $this->isStripeAccountFullyOnboarded($stripeAccount),
            'raw_requirements' => $stripeAccount->requirements?->toArray() ?? null,
        ]);

        SellerBalance::firstOrCreate(
            [
                'seller_id' => $localAccount->seller_id,
                'currency' => $localAccount->default_currency ?: 'EUR',
            ],
            [
                'pending_amount' => 0,
                'available_amount' => 0,
                'paid_out_amount' => 0,
            ]
        );

        return $localAccount->fresh();
    }

    public function isAccountFullyOnboarded(SellerPayoutAccount|string $account): bool
    {
        if (is_string($account)) {
            $account = SellerPayoutAccount::query()
                ->where('stripe_account_id', $account)
                ->firstOrFail();
        }

        return $this->syncLocalAccount($account)->isFullyOnboarded();
    }

    public function isStripeAccountFullyOnboarded(object $stripeAccount): bool
    {
        return (bool) $stripeAccount->charges_enabled
            && (bool) $stripeAccount->payouts_enabled
            && (bool) $stripeAccount->details_submitted;
    }

    public function findLocalAccountByStripeId(string $stripeAccountId): ?SellerPayoutAccount
    {
        return SellerPayoutAccount::query()
            ->where('stripe_account_id', $stripeAccountId)
            ->first();
    }

    protected function stripe(): StripeClient
    {
        if ($this->client instanceof StripeClient) {
            return $this->client;
        }

        $secret = config('services.stripe.secret');

        if (! is_string($secret) || trim($secret) === '') {
            throw new RuntimeException('Stripe secret key is not configured.');
        }

        $this->client = new StripeClient($secret);

        return $this->client;
    }
}
