<?php

namespace App\Console\Commands;

use App\Models\IdentityVerification;
use App\Models\User;
use App\Models\VerificationSubmission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PrepareDiditVerification extends Command
{
    protected $signature = 'didit:prepare {--apply : Apply the audited transition once} {--exception-user=* : Two explicitly confirmed user IDs}';
    protected $description = 'Snapshot existing verification and require Didit for KLCARDS and micadopanther; dry run by default.';

    public function handle(): int
    {
        $exceptions = [];
        $explicit = $this->option('exception-user');
        if ($explicit !== []) {
            if (count(array_unique($explicit)) !== 2 || collect($explicit)->contains(fn ($id) => ! ctype_digit((string) $id)) || User::whereIn('id', $explicit)->count() !== 2) {
                $this->error('Provide exactly two existing, confirmed user IDs.');
                return self::FAILURE;
            }
            $exceptions = array_map('intval', $explicit);
        }
        foreach ($explicit === [] ? ['klcards', 'micadopanther'] : [] as $handle) {
            $matches = User::whereRaw('LOWER(handle) = ?', [$handle])->get();
            if ($matches->count() !== 1) {
                $this->error("Expected exactly one account with handle {$handle}; no changes applied.");
                return self::FAILURE;
            }
            $exceptions[] = $matches->first()->id;
        }
        $rows = [];
        DB::transaction(function () use ($exceptions, &$rows) {
            foreach (User::orderBy('id')->lockForUpdate()->get() as $user) {
                if (IdentityVerification::where('user_id', $user->id)->exists()) continue;
                $latest = VerificationSubmission::where('user_id', $user->id)->get()
                    ->sortByDesc(fn ($s) => $s->submitted_at?->timestamp ?? $s->created_at?->timestamp ?? 0)
                    ->groupBy(fn ($s) => $s->verification_type)->map->first();
                $fullyApproved = collect(['identity', 'address', 'bank'])->every(fn ($type) => $latest->get($type)?->status === 'approved');
                $preserve = ($user->is_verified_seller || $fullyApproved) && ! in_array($user->id, $exceptions, true);
                $rows[] = [$user->id, $user->handle, $user->is_verified_seller ? 'yes' : 'no', $fullyApproved ? 'yes' : 'no', $preserve ? 'preserve' : 'Didit required'];
                if (! $this->option('apply')) continue;
                IdentityVerification::create([
                    'user_id' => $user->id, 'source' => $preserve ? 'legacy' : 'didit',
                    'status' => $preserve ? 'Approved' : 'Not Started',
                    'verified_at' => $preserve ? now() : null, 'migrated_at' => now(),
                    'expires_at' => $preserve ? now()->addYearNoOverflow() : null,
                    'legacy_snapshot' => [
                        'is_verified_seller' => $user->is_verified_seller, 'trust_status' => $user->trust_status,
                        'submissions' => $latest->map(fn ($s) => ['id' => $s->id, 'status' => $s->status])->all(),
                        'requires_reverification' => in_array($user->id, $exceptions, true),
                    ],
                ]);
                $user->forceFill(['is_verified_seller' => $preserve, 'trust_status' => $preserve ? 'trusted' : 'basic'])->save();
            }
        });
        $this->table(['ID', 'Handle', 'Verified badge', 'Three approvals', 'Transition'], $rows);
        $this->info($this->option('apply') ? 'Transition applied. Existing transition records were not changed.' : 'Dry run only. Use --apply after reviewing the exact IDs.');
        return self::SUCCESS;
    }
}
