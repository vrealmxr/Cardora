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
