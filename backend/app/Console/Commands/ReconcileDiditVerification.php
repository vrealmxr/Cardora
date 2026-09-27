<?php

namespace App\Console\Commands;

use App\Models\IdentityVerification;
use App\Services\DiditService;
use Illuminate\Console\Command;

class ReconcileDiditVerification extends Command
{
    protected $signature = 'didit:reconcile';
    protected $description = 'Recover missed Didit decisions without persisting document or biometric payloads.';

    public function handle(DiditService $didit): int
    {
        if (! config('didit.enabled')) return self::SUCCESS;
        IdentityVerification::with('user')->where('source', 'didit')->whereNotNull('current_session_id')
            ->chunkById(100, function ($records) use ($didit) {
                foreach ($records as $record) {
                    try { $didit->reconcile($record->user); }
                    catch (\Throwable $e) { $this->warn('Reconciliation deferred for user '.$record->user_id); }
                }
            });
        return self::SUCCESS;
    }
}
