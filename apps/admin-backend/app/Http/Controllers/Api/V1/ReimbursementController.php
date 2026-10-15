<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Reimbursement;
use App\Models\ReimbursementAttachment;
use App\Models\StoredFile;
use App\Services\AuditService;
use App\Services\TdocsException;
use App\Services\TdocsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReimbursementController extends Controller
{
    public function __construct(protected TdocsService $tdocs) {}

    protected function employeeOf(Request $request)
    {
        $employee = $request->user()->employee;
        if (!$employee || !$employee->isActive()) {
            abort(403, 'Akun tidak terhubung ke data karyawan aktif.');
        }
        return $employee;
    }

    /**
     * GET /api/v1/reimbursements — own claims.
     * Matches the PWA Reimbursement[] contract.
     */
    public function index(Request $request)
    {
        $employee = $this->employeeOf($request);

        $claims = Reimbursement::where('employee_id', $employee->id)
            ->with('attachments')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'items' => $claims->map(fn ($c) => [
                'id' => (string) $c->id,
                'kategori' => $c->category,
                'nominal' => (int) $c->amount,
                'tanggal' => $c->expense_date->format('Y-m-d'),
                'keterangan' => $c->description ?? '',
                'status' => $c->status,
                'ada_bukti' => $c->attachments->isNotEmpty(),
                'dibuat_at' => $c->created_at->toIso8601String(),
            ])->values(),
        ]);
    }

    /**
     * POST /api/v1/reimbursements — submit a claim (feature toggle enforced by middleware).
     *
     * Accepts the PWA field names (kategori, nominal, tanggal, keterangan, bukti)
     * as well as legacy names (category, amount, expense_date, description, receipt).
     */
    public function store(Request $request)
    {
        // Normalize PWA field aliases before validation (raw FileBag: see
        // AttendanceController::record for why file() must not be called first).
        if (!$request->files->has('receipt') && $request->files->has('bukti')) {
            $request->files->set('receipt', $request->files->get('bukti'));
        }
        foreach (['kategori' => 'category', 'nominal' => 'amount', 'tanggal' => 'expense_date', 'keterangan' => 'description'] as $alias => $canonical) {
            if ($request->input($canonical) === null && $request->input($alias) !== null) {
                $request->merge([$canonical => $request->input($alias)]);
            }
        }

        // Accept the idempotency key via header OR body field.
        if (!$request->input('idempotency_key') && $request->header('Idempotency-Key')) {
            $request->merge(['idempotency_key' => $request->header('Idempotency-Key')]);
        }

        $request->validate([
            'category' => ['required', 'string', 'max:80'],
            'amount' => ['required', 'integer', 'min:1000', 'max:100000000'],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:255'],
            'receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'idempotency_key' => ['required', 'string', 'max:80'],
        ]);

        $employee = $this->employeeOf($request);
        $key = 'u' . $request->user()->id . ':' . $request->input('idempotency_key');

        $claim = DB::transaction(function () use ($request, $employee, $key) {
            $claim = Reimbursement::create([
                'employee_id' => $employee->id,
                'category' => $request->input('category'),
                'amount' => (int) $request->input('amount'),
                'expense_date' => $request->input('expense_date'),
                'description' => $request->input('description'),
                'status' => Reimbursement::STATUS_MENUNGGU,
                'idempotency_key' => $key,
            ]);

            // Receipt -> private staging -> tDocs (same durable pattern as attendance).
            $file = $request->file('receipt');
            $ext = $file->getClientOriginalExtension() ?: 'jpg';
            $stagedName = 'staging/reimbursements/' . date('Y/m') . '/' . Str::uuid() . '.' . $ext;
            Storage::disk('local')->put($stagedName, file_get_contents($file->getRealPath()));

            $stored = StoredFile::create([
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'folder' => 'reimbursements',
                'status' => StoredFile::STATUS_PENDING,
                'uploaded_by' => $request->user()->id,
                'tdocs_folder_path' => "reimbursements/{$employee->employee_code}",
                'staged_path' => $stagedName,
            ]);

            try {
                $tmp = tempnam(sys_get_temp_dir(), 'rmb');
                file_put_contents($tmp, Storage::disk('local')->get($stagedName));
                // Fast inline attempt: a dead tDocs must never block the request;
                // the queue worker retries asynchronously.
                $result = $this->tdocs->withTimeout(8)->withoutRetries()
                    ->uploadFile($tmp, $stored->tdocs_folder_path, $stored->original_name);
                @unlink($tmp);
                $stored->update([
                    'tdocs_file_id' => $result['file_id'],
                    'status' => StoredFile::STATUS_UPLOADED,
                    'staged_path' => null,
                ]);
                Storage::disk('local')->delete($stagedName);
            } catch (\Throwable $e) {
                report($e);
                $stored->update([
                    'last_error' => substr($e->getMessage(), 0, 500),
                    'attempts' => 1,
                ]);
                \App\Jobs\RetryTdocsUpload::dispatch($stored->id)->delay(now()->addMinutes(2));
            }

            ReimbursementAttachment::create([
                'reimbursement_id' => $claim->id,
                'stored_file_id' => $stored->id,
            ]);

            AuditService::log('reimbursement.submitted', $request->user(), 'reimbursements', (string) $claim->id);

            return $claim;
        });

        return response()->json([
            'ok' => true,
            'id' => (string) $claim->id,
        ], 201);
    }

    /**
     * GET /api/v1/reimbursements/{id} — own claim detail.
     */
    public function show(Request $request, Reimbursement $reimbursement)
    {
        $this->authorize('view', $reimbursement);
        return response()->json($reimbursement->load('attachments.storedFile'));
    }
}
