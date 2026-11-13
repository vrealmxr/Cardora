<?php
namespace App\Console\Commands;

use App\Models\CardScan;
use App\Services\CardScanService;
use Illuminate\Console\Command;

class ProcessCardScans extends Command
{
    protected $signature='cardora:process-scans {--prune-only}';
    protected $description='Drain the private scan queue on demand; never keep a GPU warm';
    public function handle(CardScanService $service): int
    {
        $handle=fopen($service->root().'/worker.lock','c');
        if (!$handle || !flock($handle,LOCK_EX|LOCK_NB)) return 0;
        try {
            $service->locked('intake',fn()=>$service->prune());
            if ($this->option('prune-only') || !config('card_scans.enabled')) return 0;
            $end=time()+1200;
            while (time()<$end && ($scan=CardScan::where('state','queued')->oldest()->first())) {
                $service->process($scan);
            }
        } finally { flock($handle,LOCK_UN); fclose($handle); }
        return 0;
    }
}
