<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class VerificationSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'verification_type',
        'status',
        'payload',
        'requirements_snapshot',
        'reviewer_notes',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
    ];

    protected $casts = [
        'payload' => 'array',
        'requirements_snapshot' => 'array',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $submission): void {
            $submission->verification_type = self::normalizeVerificationType($submission->verification_type);
            $submission->status = self::normalizeStatus($submission->status);
        });

        static::saved(function (self $submission): void {
            $submission->syncUserVerificationState();
        });

        static::deleted(function (self $submission): void {
            $submission->syncUserVerificationState();
        });
    }

    public function getVerificationTypeAttribute(?string $value): ?string
    {
        return self::normalizeVerificationType($value);
    }

    public function setVerificationTypeAttribute(?string $value): void
    {
        $this->attributes['verification_type'] = self::normalizeVerificationType($value);
    }

    public function getStatusAttribute(?string $value): ?string
    {
        return self::normalizeStatus($value);
    }

    public function setStatusAttribute(?string $value): void
    {
        $this->attributes['status'] = self::normalizeStatus($value);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(VerificationDocument::class);
    }

    public static function normalizeVerificationType(?string $value): ?string
    {
        return match ($value) {
            'bank_account' => 'bank',
            default => $value,
        };
    }

    public static function normalizeStatus(?string $value): ?string
    {
        return match ($value) {
            'changes_requested' => 'needs_revision',
            default => $value,
        };
    }

    public function syncUserVerificationState(): void
    {
        if (! $this->user_id) {
            return;
        }

        $user = User::query()->find($this->user_id);

        if (! $user) {
            return;
        }

        $latestByType = self::query()
            ->where('user_id', $user->getKey())
            ->get()
            ->sortByDesc(fn (self $submission) => $submission->submitted_at?->timestamp ?? $submission->created_at?->timestamp ?? 0)
            ->groupBy(fn (self $submission) => self::normalizeVerificationType($submission->getRawOriginal('verification_type')))
            ->map(fn (Collection $group) => $group->first());

        $requiredTypes = collect(['identity', 'address', 'bank']);
        $fullyApproved = $requiredTypes->every(
            fn (string $type) => self::normalizeStatus($latestByType->get($type)?->getRawOriginal('status')) === 'approved'
        );

        $hasOpenVerification = $latestByType->contains(
            fn (?self $submission) => in_array(
                self::normalizeStatus($submission?->getRawOriginal('status')),
                ['submitted', 'under_review', 'needs_revision'],
                true
            )
        );

        $user->forceFill([
            'is_verified_seller' => $fullyApproved,
            'trust_status' => $fullyApproved
                ? 'trusted'
                : ($hasOpenVerification ? 'reviewing' : ($user->trust_status === 'trusted' ? 'basic' : ($user->trust_status ?: 'basic'))),
        ])->save();
    }
}
