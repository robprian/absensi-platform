<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reimbursement;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

class ReimbursementAdminController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Reimbursement::class);

        $q = Reimbursement::with(['employee', 'reviewer', 'attachments.storedFile'])
            ->when($request->status, fn ($qq, $s) => $qq->where('status', $s))
            ->when($request->employee_id, fn ($qq, $id) => $qq->where('employee_id', $id))
            ->orderByDesc('created_at');

        $claims = $q->paginate(20);
        $claims->getCollection()->transform(function ($c) {
            $c->receipt_url = $c->attachments->first()?->storedFile?->isUploaded()
                ? URL::temporarySignedRoute('api.v1.admin.files.preview', now()->addMinutes(10), [
                    'file' => $c->attachments->first()->storedFile->id,
                ]) : null;
            return $c;
        });

        return response()->json($claims);
    }

    public function show(Reimbursement $reimbursement)
    {
        $this->authorize('view', $reimbursement);
        return response()->json($reimbursement->load(['employee', 'reviewer', 'attachments.storedFile']));
    }

    /**
     * POST /api/v1/admin/reimbursements/{id}/transition {status: disetujui|ditolak|dibayar}
     * Every transition is audit-logged; payouts cannot be duplicated.
     */
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
            return response()->json([
                'message' => "Status tidak bisa diubah dari [{$reimbursement->status}] ke [$to].",
            ], 422);
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
            'reimbursement.' . $to,
            $request->user(),
            'reimbursements',
            (string) $reimbursement->id,
            ['status' => $before],
            ['status' => $to]
        );

        return response()->json([
            'message' => 'Status reimburse diperbarui.',
            'claim' => $reimbursement->fresh(),
        ]);
    }
}
