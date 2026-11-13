<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'body',
        'body_masked',
        'attachments',
        'offer_amount',
        'metadata',
        'moderation_status',
        'moderation_flags',
        'moderation_score',
        'requires_admin_review',
        'reviewed_at',
        'reviewed_by',
        'read_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'offer_amount' => 'decimal:2',
        'metadata' => 'array',
        'moderation_flags' => 'array',
        'requires_admin_review' => 'boolean',
        'reviewed_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    // Older versions of the admin tag editor sometimes persisted CSV inside JSON.
    public function getModerationFlagsAttribute($raw): array
    {
        $value = $raw;
        for ($depth = 0; $depth < 2 && is_string($value); $depth++) {
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $value = explode(',', $value);
                break;
            }
            $value = $decoded;
        }
        return array_values(array_filter(array_map('trim', array_filter((array) $value, 'is_string')), fn ($flag) => $flag !== ''));
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
