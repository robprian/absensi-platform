<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Payslip;
use App\Models\SystemSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class PayslipController extends Controller
{
    protected function employeeOf(Request $request)
    {
        $employee = $request->user()->employee;
        if (!$employee || !$employee->isActive()) {
            abort(403, 'Akun tidak terhubung ke data karyawan aktif.');
        }
        return $employee;
    }

    /**
     * GET /api/v1/payslips — employees see ONLY finalized+published payslips.
     * Matches the PWA PayslipSummary[] contract.
     */
    public function index(Request $request)
    {
        $employee = $this->employeeOf($request);

        $payslips = Payslip::where('published', true)
            ->whereHas('item', fn ($q) => $q
                ->where('employee_id', $employee->id)
                ->whereHas('run', fn ($r) => $r->whereIn('status', ['finalized', 'locked'])))
            ->with(['item.run.period'])
            ->orderByDesc('published_at')
            ->get();

        return response()->json([
            'items' => $payslips->map(fn ($p) => $this->formatSummary($p))->values(),
        ]);
    }

    /**
     * GET /api/v1/payslips/{id} — PayslipDetail for the PWA slip page.
     */
    public function show(Request $request, Payslip $payslip)
    {
        $this->authorizePayslip($request, $payslip);

        return response()->json($this->formatDetail($payslip));
    }

    /**
     * GET /api/v1/payslips/{id}/download-url — short-lived signed PDF URL.
     */
    public function downloadUrl(Request $request, Payslip $payslip)
    {
        $this->authorizePayslip($request, $payslip);

        $expiresAt = now()->addMinutes(10);

        return response()->json([
            'url' => URL::temporarySignedRoute(
                'api.v1.payslips.download',
                $expiresAt,
                ['payslip' => $payslip->id]
            ),
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/payroll/payslips/{id}/download (signed, 10 min)
     */
    public function download(Request $request, Payslip $payslip)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'Tautan unduhan tidak valid atau kedaluwarsa.');
        }
        $this->authorizePayslip($request, $payslip);

        $pdf = $this->renderPdf($payslip);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $payslip->reference_code . '.pdf"',
        ]);
    }

    protected function authorizePayslip(Request $request, Payslip $payslip): void
    {
        $employee = $this->employeeOf($request);
        $item = $payslip->item()->with('run')->firstOrFail();

        $ok = $payslip->published
            && in_array($item->run->status, ['finalized', 'locked'], true)
            && (int) $item->employee_id === (int) $employee->id;

        if (!$ok) {
            abort(404);
        }
    }

    protected function formatSummary(Payslip $payslip): array
    {
        $item = $payslip->item;
        $period = $item->run->period;

        return [
            'id' => (string) $payslip->id,
            'periode' => $period->name,
            'tanggal_mulai' => $period->starts_on->format('Y-m-d'),
            'tanggal_selesai' => $period->ends_on->format('Y-m-d'),
            'total_diterima' => (int) $item->net,
            'status_bayar' => $item->run->status === 'locked' ? 'dibayar' : 'belum',
        ];
    }

    protected function formatDetail(Payslip $payslip): array
    {
        $item = $payslip->item()->with(['run.period', 'employee'])->firstOrFail();
        $summary = $this->formatSummary($payslip);

        $rincian = [];
        $add = function (string $label, int $nominal, string $tipe) use (&$rincian) {
            if ($nominal > 0) {
                $rincian[] = ['label' => $label, 'nominal' => $nominal, 'tipe' => $tipe];
            }
        };

        $add('Bonus', (int) $item->bonuses, 'tambah');
        $add('Reimburse Dibayar via Payroll', (int) $item->reimbursements, 'tambah');

        return array_merge($summary, [
            'nomor_dokumen' => $payslip->reference_code,
            'gaji_pokok' => (int) $item->base_salary,
            'insentif_kehadiran' => (int) $item->attendance_comp,
            'lembur' => (int) $item->overtime,
            'tunjangan' => (int) $item->allowances,
            'potongan' => (int) $item->deductions,
            'reimburse_disetujui' => (int) $item->reimbursements,
            'rincian' => $rincian,
        ]);
    }

    public function renderPdf(Payslip $payslip)
    {
        $item = $payslip->item()->with(['employee', 'run.period'])->firstOrFail();

        return Pdf::loadView('pdf.payslip', [
            'payslip' => $payslip,
            'item' => $item,
            'employee' => $item->employee,
            'period' => $item->run->period,
            'company' => [
                'name' => SystemSetting::get('company_name', 'PT Contoh Sejahtera'),
                'address' => SystemSetting::get('company_address', ''),
                'phone' => SystemSetting::get('company_phone', ''),
            ],
        ])->setPaper('a4');
    }

    public static function formatRupiah(int $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
}
