<?php

namespace App\Console\Commands;

use App\Jobs\RetryTdocsUpload;
use App\Models\StoredFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Storage reconciliation:
 *  - re-dispatch retries for pending/failed uploads that still have a staged copy
 *  - delete orphaned staging files older than 7 days with no matching record
 */
class ReconcileStorage extends Command
{
    protected $signature = 'storage:reconcile';
    protected $description = 'Rekonsiliasi upload tDocs yang tertunda dan bersihkan staging yatim';

    public function handle(): int
    {
        $pending = StoredFile::whereIn('status', [StoredFile::STATUS_PENDING, StoredFile::STATUS_FAILED])
            ->whereNotNull('staged_path')
            ->where('attempts', '<', 10)
            ->get();

        $redispatched = 0;
        foreach ($pending as $file) {
            if (Storage::disk('local')->exists($file->staged_path)) {
                RetryTdocsUpload::dispatch($file->id);
                $redispatched++;
            } else {
                $file->update([
                    'status' => StoredFile::STATUS_FAILED,
                    'last_error' => 'Staged copy hilang; tidak bisa retry.',
                ]);
            }
        }

        // Orphaned staging files (no record references them, older than 7 days).
        $known = StoredFile::whereNotNull('staged_path')->pluck('staged_path')->all();
        $orphans = 0;
        foreach (Storage::disk('local')->allFiles('staging') as $path) {
            if (!in_array($path, $known, true)
                && Storage::disk('local')->lastModified($path) < now()->subDays(7)->timestamp) {
                Storage::disk('local')->delete($path);
                $orphans++;
            }
        }

        // Prune expired idempotency records.
        $pruned = \App\Models\IdempotencyRecord::where('expires_at', '<', now())->delete();

        $this->info("Redispatched: $redispatched, orphans cleaned: $orphans, idempotency pruned: $pruned");
        return self::SUCCESS;
    }
}
