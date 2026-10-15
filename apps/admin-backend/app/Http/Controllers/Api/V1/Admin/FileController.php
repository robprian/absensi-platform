<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\StoredFile;
use App\Services\TdocsException;
use App\Services\TdocsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Secure file proxy. Files are NEVER exposed via public URLs.
 * Access requires a short-lived signed URL (10 min) + policy check.
 */
class FileController extends Controller
{
    public function __construct(protected TdocsService $tdocs) {}

    /**
     * GET /api/v1/admin/files/{file}/preview (signed)
     */
    public function preview(Request $request, StoredFile $file)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'Tautan tidak valid atau kedaluwarsa.');
        }

        // Any staff with document/attendance view permission may preview.
        $user = $request->user();
        if (!$user->hasPermission('documents.view')
            && !$user->hasPermission('attendance.view')
            && !$user->hasPermission('reimbursements.review')) {
            abort(403);
        }

        // Fall back to the staged copy if tDocs upload is still pending.
        if (!$file->isUploaded() && $file->staged_path && Storage::disk('local')->exists($file->staged_path)) {
            return response()->file(
                Storage::disk('local')->path($file->staged_path),
                ['Content-Type' => $file->mime_type]
            );
        }

        try {
            $bytes = $this->tdocs->downloadBytes((string) $file->tdocs_file_id);
        } catch (TdocsException $e) {
            abort(502, 'Gagal mengambil file dari penyimpanan.');
        }

        return response($bytes, 200, [
            'Content-Type' => $file->mime_type,
            'Content-Disposition' => 'inline; filename="' . $file->original_name . '"',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    /**
     * GET /api/v1/admin/files/{file}/download (signed)
     */
    public function download(Request $request, StoredFile $file)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'Tautan tidak valid atau kedaluwarsa.');
        }

        $user = $request->user();
        if (!$user->hasPermission('documents.view')
            && !$user->hasPermission('attendance.view')
            && !$user->hasPermission('reimbursements.review')) {
            abort(403);
        }

        try {
            $bytes = $this->tdocs->downloadBytes((string) $file->tdocs_file_id);
        } catch (TdocsException $e) {
            abort(502, 'Gagal mengambil file dari penyimpanan.');
        }

        return response($bytes, 200, [
            'Content-Type' => $file->mime_type,
            'Content-Disposition' => 'attachment; filename="' . $file->original_name . '"',
        ]);
    }

    /**
     * GET /api/v1/admin/tdocs/health — storage health check.
     */
    public function tdocsHealth(Request $request)
    {
        try {
            $health = $this->tdocs->healthCheck();
            return response()->json(['ok' => true, 'tdocs' => $health]);
        } catch (TdocsException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 502);
        }
    }
}
