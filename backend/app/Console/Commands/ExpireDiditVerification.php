<?php

namespace App\Console\Commands;

use App\Models\DiditSession;
use App\Models\IdentityVerification;
use App\Services\DiditRetentionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ExpireDiditVerification extends Command
{
    protected $signature = 'didit:expire {--delete : Also delete expired provider sessions}';
    protected $description = 'Enforce annual verification expiry and retry due Didit session deletions.';

    public function handle(DiditRetentionService $retention): int
    {
        Cache::put('didit:last_expiry_check', now()->toIso8601String(), now()->addDays(2));
        if (! config('didit.enabled')) return self::SUCCESS;
        IdentityVerification::where('expires_at', '<=', now())->where('status', '!=', 'Kyc Expired')
            ->chunkById(100, function ($records) use ($retention) {
                foreach ($records as $record) $retention->expireUser($record->user_id);
            });
        $failed = 0;
        if ($this->option('delete')) {
            if (blank(config('didit.api_key'))) return self::FAILURE;
            DiditSession::where('retention_due_at', '<=', now())->whereNull('session_deleted_at')
                ->where(fn ($q) => $q->whereNull('deletion_attempted_at')->orWhere('deletion_attempted_at', '<=', now()->subHour()))
                ->chunkById(100, function ($sessions) use ($retention, &$failed) {
                    foreach ($sessions as $session) {
                        if (! $retention->deleteExpiredSession($session)) $failed++;
                    }
                });
        }
        $this->info('Annual expiry checked; failed provider deletions: '.$failed);
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
