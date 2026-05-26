<?php

namespace App\Services;

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

        $requirements = [
            $this->verificationRequirement('identity', $latestVerificationSubmissions->get('identity'), $locale),
            $this->verificationRequirement('address', $latestVerificationSubmissions->get('address'), $locale),
            $this->verificationRequirement('bank', $latestVerificationSubmissions->get('bank'), $locale),
            $this->stripeRequirement($stripeAccount, $locale),
        ];

        $readyCount = collect($requirements)->where('ready', true)->count();
        $totalCount = count($requirements);
        $missingRequirements = collect($requirements)
            ->filter(fn (array $requirement) => ! $requirement['ready'])
            ->values();
        $isMarketplaceReady = $missingRequirements->isEmpty();

        return [
            'is_marketplace_ready' => $isMarketplaceReady,
            'can_buy' => $isMarketplaceReady,
            'can_sell' => $isMarketplaceReady,
            'can_checkout' => $isMarketplaceReady,
            'can_create_listing' => $isMarketplaceReady,
            'can_bid' => $isMarketplaceReady,
            'can_join_draws' => $isMarketplaceReady,
            'completion_percentage' => (int) round(($readyCount / max($totalCount, 1)) * 100),
            'ready_count' => $readyCount,
            'total_requirements' => $totalCount,
            'missing_keys' => $missingRequirements->pluck('key')->all(),
            'blocking_message' => $this->blockingMessage($missingRequirements->all(), $locale),
            'requirements' => $requirements,
            'verification' => [
                'identity' => $requirements[0],
                'address' => $requirements[1],
                'bank' => $requirements[2],
            ],
            'stripe_connect' => $requirements[3],
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

    protected function normalizeLocale(?string $locale): string
    {
        return in_array($locale, ['el', 'en'], true)
            ? $locale
            : app()->getLocale();
    }
}
