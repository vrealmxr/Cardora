<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiditSession extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['verification_url'];
    protected $casts = [
        'verification_url' => 'encrypted',
        'consented_at' => 'datetime',
        'last_reconciled_at' => 'datetime',
        'provider_updated_at' => 'integer',
        'retention_due_at' => 'datetime',
        'deletion_requested_at' => 'datetime',
        'session_deleted_at' => 'datetime',
        'deletion_attempted_at' => 'datetime',
    ];
}
