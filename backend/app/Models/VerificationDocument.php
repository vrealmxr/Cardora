<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class VerificationDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'verification_submission_id',
        'document_type',
        'storage_disk',
        'storage_path',
        'original_name',
        'mime_type',
        'file_size',
        'metadata',
        'uploaded_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'uploaded_at' => 'datetime',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(VerificationSubmission::class, 'verification_submission_id');
    }

    public function getFileUrl(): ?string
    {
        if (! $this->storage_disk || ! $this->storage_path) {
            return null;
        }

        return Storage::disk($this->storage_disk)->url($this->storage_path);
    }
}
