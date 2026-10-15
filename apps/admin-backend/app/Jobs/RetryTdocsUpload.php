<?php

namespace App\Jobs;

use App\Models\StoredFile;
use App\Services\TdocsException;
use App\Services\TdocsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Retries a failed/pending tDocs upload from the private staging area.
 * Cleanup of orphaned staging files happens in the scheduled
 * `storage:reconcile` command.
 */
class RetryTdocsUpload implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $backoff = 120;

    public function __construct(public int $storedFileId) {}

    public function handle(TdocsService $tdocs): void
    {
        $file = StoredFile::find($this->storedFileId);
        if (!$file || $file->status === StoredFile::STATUS_UPLOADED) {
            return;
        }

        // Find the staged copy: we stored its path implicitly via naming;
        // locate by scanning today's staging dir is fragile, so we keep the
        // original staging path on the record when staging.
        $stagedPath = $file->getAttribute('staged_path');
        if (!$stagedPath || !Storage::disk('local')->exists($stagedPath)) {
            $file->update([
                'status' => StoredFile::STATUS_FAILED,
                'last_error' => 'Staged copy missing; cannot retry upload.',
            ]);
            return;
        }

        try {
            $tmp = tempnam(sys_get_temp_dir(), 'absen');
            file_put_contents($tmp, Storage::disk('local')->get($stagedPath));
            $result = $tdocs->uploadFile($tmp, (string) $file->tdocs_folder_path, $file->original_name);
            @unlink($tmp);
            $file->update([
                'tdocs_file_id' => $result['file_id'],
                'status' => StoredFile::STATUS_UPLOADED,
                'attempts' => $file->attempts + 1,
                'last_error' => null,
            ]);
            Storage::disk('local')->delete($stagedPath);
        } catch (TdocsException $e) {
            $file->update([
                'attempts' => $file->attempts + 1,
                'last_error' => substr($e->getMessage(), 0, 500),
            ]);
            throw $e; // let the queue backoff retry
        }
    }
}
