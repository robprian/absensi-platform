<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reimbursement;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

class ReimbursementController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Reimbursement::class);

        $claims = Reimbursement::with(['employee', 'reviewer', 'attachments.storedFile'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        foreach ($claims as $claim) {
            $file = $claim->attachments->first()?->storedFile;
            $claim->receipt_url = $file?->isUploaded()
                ? URL::temporarySignedRoute('admin.files.preview', now()->addMinutes(30), ['file' => $file->id])
                : null;
        }

        return view('admin.reimbursements.index', compact('claims'));
    }

    public function show(Reimbursement $reimbursement)
    {
        $this->authorize('view', $reimbursement);
        $reimbursement->load(['employee', 'reviewer', 'attachments.storedFile']);

        $file = $reimbursement->attachments->first()?->storedFile;
        $receiptUrl = $file?->isUploaded()
            ? URL::temporarySignedRoute('admin.files.preview', now()->addMinutes(30), ['file' => $file->id])
            : null;

        return view('admin.reimbursements.show', compact('reimbursement', 'receiptUrl'));
    }

    public function transition(Request $request, Reimbursement $reimbursement)
    {
        $this->authorize('review', $reimbursement);

        $data = $request->validate([
            'status' => ['required', Rule::in([
                Reimbursement::STATUS_DISETUJUI,
                Reimbursement::STATUS_DITOLAK,
                Reimbursement::STATUS_DIBAYAR,
            ])],
            'review_note' => ['nullable', 'string', 'max:255'],
        ]);

        $to = $data['status'];
        if (!$reimbursement->canTransitionTo($to)) {
            return back()->with('err', "Status tidak bisa diubah dari [{$reimbursement->status}] ke [$to].");
        }

        $before = $reimbursement->status;
        $reimbursement->update([
            'status' => $to,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $data['review_note'] ?? null,
            'paid_at' => $to === Reimbursement::STATUS_DIBAYAR ? now() : $reimbursement->paid_at,
        ]);

        AuditService::log(
            'reimbursement.' . $to, $request->user(), 'reimbursements', (string) $reimbursement->id,
            ['status' => $before], ['status' => $to]
        );

        return back()->with('ok', 'Status reimburse diperbarui.');
    }
}
