<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\SellerPayoutAccount;
use App\Models\User;
use App\Models\VerificationSubmission;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MarketplaceAccessService
{
    public function summaryForUser(User $user, ?string $locale = null): array
    {
        $locale = $this->normalizeLocale($locale);
        $latestVerificationSubmissions = $this->latestVerificationSubmissions($user);
        $stripeAccount = $user->relationLoaded('sellerPayoutAccount')
            ? $user->sellerPayoutAccount
            : $user->sellerPayoutAccount()->first();

        $buyRequirements = [
            $this->verificationRequirement('identity', $latestVerificationSubmissions->get('identity'), $locale),
            $this->verificationRequirement('address', $latestVerificationSubmissions->get('address'), $locale),
            $this->verificationRequirement('bank', $latestVerificationSubmissions->get('bank'), $locale),
            $this->stripeRequirement($stripeAccount, $locale),
        ];
        $sellerShippingRequirement = $this->sellerShippingOriginRequirement($user, $locale);
        $requirements = [...$buyRequirements, $sellerShippingRequirement];

        $readyCount = collect($requirements)->where('ready', true)->count();
        $totalCount = count($requirements);
        $missingRequirements = collect($requirements)
            ->filter(fn (array $requirement) => ! $requirement['ready'])
            ->values();
        $missingBuyRequirements = collect($buyRequirements)
            ->filter(fn (array $requirement) => ! $requirement['ready'])
            ->values();
        $canBuy = $missingBuyRequirements->isEmpty();
        $canSell = $canBuy && $sellerShippingRequirement['ready'];
        $isMarketplaceReady = $canSell;

        return [
            'is_marketplace_ready' => $isMarketplaceReady,
            'can_buy' => $canBuy,
            'can_sell' => $canSell,
            'can_checkout' => $canBuy,
            'can_create_listing' => $canSell,
            'can_bid' => $canBuy,
            'can_join_draws' => $canBuy,
            'completion_percentage' => (int) round(($readyCount / max($totalCount, 1)) * 100),
            'ready_count' => $readyCount,
            'total_requirements' => $totalCount,
            'missing_keys' => $missingRequirements->pluck('key')->all(),
            'blocking_message' => $canBuy
                ? $this->sellerBlockingMessage($missingRequirements->all(), $locale)
                : $this->blockingMessage($missingBuyRequirements->all(), $locale),
            'requirements' => $requirements,
            'verification' => [
                'identity' => $requirements[0],
                'address' => $requirements[1],
                'bank' => $requirements[2],
            ],
            'stripe_connect' => $requirements[3],
            'seller_shipping_origin' => $requirements[4],
        ];
    }

    public function assertCanBuy(User $user, ?string $locale = null): void
    {
        $summary = $this->summaryForUser($user, $locale);

        if ($summary['can_buy']) {
            return;
        }

        throw ValidationException::withMessages([
            'marketplace' => [$summary['blocking_message']],
        ]);
    }

    public function assertCanSell(User $user, ?string $locale = null): void
    {
        $summary = $this->summaryForUser($user, $locale);

        if ($summary['can_sell']) {
            return;
        }

        throw ValidationException::withMessages([
            'marketplace' => [$summary['blocking_message']],
        ]);
    }

    public function missingSellerShippingOriginFields(User $user): array
    {
        $origin = is_array($user->shipping_origin) ? $user->shipping_origin : [];
        $missingFields = [];

        if (blank($origin['phone'] ?? $user->phone)) {
            $missingFields[] = 'phone';
        }

        if (blank($origin['address_line_1'] ?? null)) {
            $missingFields[] = 'address_line_1';
        }

        if (blank($origin['city'] ?? $user->city)) {
            $missingFields[] = 'city';
        }

        if (blank($origin['postal_code'] ?? null)) {
            $missingFields[] = 'postal_code';
        }

        return $missingFields;
    }

    public function hasRequiredSellerShippingOrigin(User $user): bool
    {
        return $this->missingSellerShippingOriginFields($user) === [];
    }

    public function listingIsPubliclyVisible(Listing $listing): bool
    {
        if (! in_array((string) $listing->status, ['active', 'published'], true)) {
            return false;
        }

        $seller = $listing->relationLoaded('seller')
            ? $listing->seller
            : $listing->seller()->first();

        return $seller instanceof User && $this->hasRequiredSellerShippingOrigin($seller);
    }

    protected function latestVerificationSubmissions(User $user): Collection
    {
        return VerificationSubmission::query()
            ->where('user_id', $user->getKey())
            ->get()
            ->sortByDesc(
                fn (VerificationSubmission $submission) => $submission->submitted_at?->timestamp
                    ?? $submission->updated_at?->timestamp
                    ?? $submission->created_at?->timestamp
                    ?? 0
            )
            ->groupBy(fn (VerificationSubmission $submission) => VerificationSubmission::normalizeVerificationType(
                $submission->getRawOriginal('verification_type') ?: $submission->verification_type
            ))
            ->map(fn (Collection $group) => $group->first());
    }

    protected function verificationRequirement(
        string $type,
        ?VerificationSubmission $submission,
        string $locale
    ): array {
        $statusKey = $this->verificationStatusKey($submission);
        $ready = $statusKey === 'approved';

        return [
            'key' => $type,
            'label' => $this->requirementLabel($type, $locale),
            'status_key' => $statusKey,
            'status_label' => $this->statusLabel($statusKey, $locale),
            'ready' => $ready,
            'description' => $this->verificationDescription($type, $statusKey, $locale),
            'action_label' => $ready
                ? ($locale === 'en' ? 'Completed' : 'Ολοκληρώθηκε')
                : ($locale === 'en' ? 'Open verification center' : 'Άνοιγμα verification center'),
            'action_path' => '/epalithefsi-logariasmou',
        ];
    }

    protected function stripeRequirement(?SellerPayoutAccount $account, string $locale): array
    {
        $hasAccount = (bool) $account?->stripe_account_id;
        $fullyReady = $account?->isFullyOnboarded() ?? false;
        $statusKey = ! $hasAccount ? 'missing' : ($fullyReady ? 'approved' : 'pending');

        return [
            'key' => 'stripe_connect',
            'label' => $locale === 'en' ? 'Stripe connected account' : 'Stripe Connected Account',
            'status_key' => $statusKey,
            'status_label' => $this->statusLabel($statusKey, $locale),
            'ready' => $fullyReady,
            'description' => $fullyReady
                ? ($locale === 'en'
                    ? 'Stripe Connect is ready for protected releases and seller payouts.'
                    : 'Το Stripe Connect είναι έτοιμο για protected releases και seller payouts.')
                : (! $hasAccount
                    ? ($locale === 'en'
                        ? 'Create your Stripe connected account so Cardora can release funds through Stripe after buyer confirmation.'
                        : 'Δημιούργησε Stripe connected account για να δουλέψει το escrow και οι αποδεσμεύσεις.')
                    : ($locale === 'en'
                        ? 'Finish the Stripe onboarding steps so transfers and payouts can be enabled.'
                        : 'Ολοκλήρωσε το onboarding του Stripe ώστε να ενεργοποιηθούν transfers και payouts.')),
            'action_label' => ! $hasAccount
                ? ($locale === 'en' ? 'Create Stripe account' : 'Δημιουργία Stripe account')
                : ($fullyReady
                    ? ($locale === 'en' ? 'Ready' : 'Έτοιμο')
                    : ($locale === 'en' ? 'Complete Stripe setup' : 'Ολοκλήρωση Stripe setup')),
            'action_path' => '/dashboard-politi',
            'stripe_account_id' => $account?->stripe_account_id,
            'onboarding_completed' => (bool) $account?->onboarding_completed,
            'charges_enabled' => (bool) $account?->charges_enabled,
            'payouts_enabled' => (bool) $account?->payouts_enabled,
            'details_submitted' => (bool) $account?->details_submitted,
        ];
    }

    protected function sellerShippingOriginRequirement(User $user, string $locale): array
    {
        $missingFields = $this->missingSellerShippingOriginFields($user);
        $ready = $missingFields === [];
        $fieldLabels = collect($missingFields)
            ->map(fn (string $field) => $this->shippingOriginFieldLabel($field, $locale))
            ->implode(', ');

        return [
            'key' => 'seller_shipping_origin',
            'label' => $locale === 'en' ? 'Private parcel shipping details' : 'Ιδιωτικά στοιχεία αποστολής δεμάτων',
            'status_key' => $ready ? 'approved' : 'missing',
            'status_label' => $this->statusLabel($ready ? 'approved' : 'missing', $locale),
            'ready' => $ready,
            'description' => $ready
                ? ($locale === 'en'
                    ? 'Your private shipping details are complete, so your listings can stay visible and your parcel shipments can be processed safely.'
                    : 'Τα ιδιωτικά στοιχεία αποστολής είναι πλήρη, οπότε οι αγγελίες σου μπορούν να παραμένουν ορατές και οι αποστολές δεμάτων να εξυπηρετούνται με ασφάλεια.')
                : ($locale === 'en'
                    ? sprintf('Add the missing private shipping details to keep listings visible and enable safe parcel shipping: %s.', $fieldLabels)
                    : sprintf('Συμπλήρωσε τα ελλιπή ιδιωτικά στοιχεία αποστολής για να παραμένουν ορατές οι αγγελίες σου και να ενεργοποιείται η ασφαλής αποστολή δεμάτων: %s.', $fieldLabels)),
            'action_label' => $locale === 'en' ? 'Complete shipping details' : 'Συμπλήρωση στοιχείων αποστολής',
            'action_path' => '/profil',
            'missing_fields' => $missingFields,
        ];
    }

    protected function verificationStatusKey(?VerificationSubmission $submission): string
    {
        if (! $submission) {
            return 'missing';
        }

        return match (VerificationSubmission::normalizeStatus($submission->getRawOriginal('status') ?: $submission->status)) {
            'approved' => 'approved',
            'submitted', 'under_review' => 'pending',
            'needs_revision' => 'needs_revision',
            'rejected' => 'rejected',
            default => 'missing',
        };
    }

    protected function requirementLabel(string $type, string $locale): string
    {
        return match ($type) {
            'identity' => $locale === 'en' ? 'Identity verification' : 'Επαλήθευση ταυτότητας',
            'address' => $locale === 'en' ? 'Address verification' : 'Επαλήθευση διεύθυνσης',
            'bank' => $locale === 'en' ? 'IBAN / bank verification' : 'Επαλήθευση IBAN / τράπεζας',
            default => $type,
        };
    }

    protected function verificationDescription(string $type, string $statusKey, string $locale): string
    {
        if ($locale === 'en') {
            return match ($statusKey) {
                'approved' => match ($type) {
                    'identity' => 'Your identity documents have been approved.',
                    'address' => 'Your address documents have been approved.',
                    'bank' => 'Your IBAN and bank ownership details have been approved.',
                    default => 'Approved.',
                },
                'pending' => 'Your submission is under review by the Cardora team.',
                'needs_revision' => 'A clearer file or corrected information is needed before approval.',
                'rejected' => 'This section was rejected and needs a new submission.',
                default => match ($type) {
                    'identity' => 'Submit identity documents before marketplace activity is unlocked.',
                    'address' => 'Submit proof of address before marketplace activity is unlocked.',
                    'bank' => 'Submit your IBAN and bank ownership proof before marketplace activity is unlocked.',
                    default => 'This requirement is still missing.',
                },
            };
        }

        return match ($statusKey) {
            'approved' => match ($type) {
                'identity' => 'Η ταυτότητα έχει εγκριθεί.',
                'address' => 'Η διεύθυνση έχει εγκριθεί.',
                'bank' => 'Το IBAN και τα στοιχεία τράπεζας έχουν εγκριθεί.',
                default => 'Έχει εγκριθεί.',
            },
            'pending' => 'Η υποβολή σου βρίσκεται σε έλεγχο από την ομάδα της Cardora.',
            'needs_revision' => 'Χρειάζεται πιο καθαρό αρχείο ή διορθωμένα στοιχεία πριν εγκριθεί.',
            'rejected' => 'Η ενότητα απορρίφθηκε και χρειάζεται νέα υποβολή.',
            default => match ($type) {
                'identity' => 'Ανέβασε ταυτότητα για να ξεκλειδώσει το marketplace.',
                'address' => 'Ανέβασε αποδεικτικό διεύθυνσης για να ξεκλειδώσει το marketplace.',
                'bank' => 'Ανέβασε IBAN και στοιχείο τράπεζας για να ξεκλειδώσει το marketplace.',
                default => 'Η ενότητα λείπει ακόμη.',
            },
        };
    }

    protected function statusLabel(string $statusKey, string $locale): string
    {
        if ($locale === 'en') {
            return match ($statusKey) {
                'approved' => 'Ready',
                'pending' => 'In review',
                'needs_revision' => 'Needs revision',
                'rejected' => 'Rejected',
                default => 'Missing',
            };
        }

        return match ($statusKey) {
            'approved' => 'Έτοιμο',
            'pending' => 'Σε έλεγχο',
            'needs_revision' => 'Χρειάζεται διόρθωση',
            'rejected' => 'Απορρίφθηκε',
            default => 'Λείπει',
        };
    }

    protected function blockingMessage(array $missingRequirements, string $locale): string
    {
        if ($missingRequirements === []) {
            return $locale === 'en'
                ? 'Marketplace access is ready.'
                : 'Η πρόσβαση στο marketplace είναι έτοιμη.';
        }

        $labels = collect($missingRequirements)
            ->pluck('label')
            ->implode(', ');

        if ($locale === 'en') {
            return sprintf(
                'To buy or sell on Cardora you must complete the following first: %s.',
                $labels
            );
        }

        return sprintf(
            'Για να αγοράζεις ή να πουλάς στην Cardora πρέπει πρώτα να ολοκληρώσεις: %s.',
            $labels
        );
    }

    protected function sellerBlockingMessage(array $missingRequirements, string $locale): string
    {
        if ($missingRequirements === []) {
            return $locale === 'en'
                ? 'Seller access is ready.'
                : 'Η πρόσβαση πωλητή είναι έτοιμη.';
        }

        $labels = collect($missingRequirements)
            ->pluck('label')
            ->implode(', ');

        if ($locale === 'en') {
            return sprintf(
                'To publish or keep listings visible on Cardora, complete the following required shipping details: %s.',
                $labels
            );
        }

        return sprintf(
            'Για να δημοσιεύεις ή να παραμένουν ορατές οι αγγελίες σου στην Cardora, ολοκλήρωσε τα εξής υποχρεωτικά στοιχεία αποστολής: %s.',
            $labels
        );
    }

    protected function shippingOriginFieldLabel(string $field, string $locale): string
    {
        return match ($field) {
            'phone' => $locale === 'en' ? 'sender phone' : 'τηλέφωνο αποστολέα',
            'address_line_1' => $locale === 'en' ? 'address line 1' : 'διεύθυνση 1',
            'city' => $locale === 'en' ? 'city' : 'πόλη',
            'postal_code' => $locale === 'en' ? 'postal code' : 'ταχυδρομικός κώδικας',
            default => $field,
        };
    }

    protected function normalizeLocale(?string $locale): string
    {
        return in_array($locale, ['el', 'en'], true)
            ? $locale
            : app()->getLocale();
    }
}
