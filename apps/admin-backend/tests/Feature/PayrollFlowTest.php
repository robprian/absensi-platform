<?php

namespace Tests\Feature;

use App\Models\AttendanceEvent;
use App\Models\AttendanceSession;
use App\Models\CompensationPolicy;
use App\Models\EmployeeCompensationRule;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\Role;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\MakesTestUsers;

class PayrollFlowTest extends TestCase
{
    use RefreshDatabase, MakesTestUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBase();
    }

    protected function seedWorkData($employee): PayrollPeriod
    {
        $period = PayrollPeriod::create([
            'name' => 'Oktober 2026', 'period_type' => 'monthly',
            'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31', 'status' => 'open',
        ]);

        // 20 closed sessions, 8h each.
        for ($d = 1; $d <= 20; $d++) {
            $date = '2026-10-' . str_pad((string) $d, 2, '0', STR_PAD_LEFT);
            $session = AttendanceSession::create([
                'employee_id' => $employee->id,
                'work_date' => $date,
                'status' => 'closed',
                'total_minutes' => 480,
            ]);
            $in = CarbonImmutable::parse("$date 08:00:00", 'Asia/Jakarta')->utc();
            AttendanceEvent::create([
                'attendance_session_id' => $session->id,
                'employee_id' => $employee->id,
                'event_type' => 'MASUK',
                'server_at' => $in,
                'latitude' => -6.2088, 'longitude' => 106.8456, 'accuracy_meters' => 8,
                'idempotency_key' => "seed-in-$d",
            ]);
            AttendanceEvent::create([
                'attendance_session_id' => $session->id,
                'employee_id' => $employee->id,
                'event_type' => 'KELUAR',
                'server_at' => $in->addHours(9),
                'latitude' => -6.2088, 'longitude' => 106.8456, 'accuracy_meters' => 8,
                'idempotency_key' => "seed-out-$d",
            ]);
        }

        $base = CompensationPolicy::create(['name' => 'Gaji Pokok', 'policy_type' => 'monthly_base', 'amount' => 2000000]);
        $att = CompensationPolicy::create([
            'name' => 'Insentif Kehadiran', 'policy_type' => 'per_attendance', 'amount' => 80000,
            'applies_when' => ['requires_clock_out' => true],
        ]);
        foreach ([$base, $att] as $p) {
            EmployeeCompensationRule::create([
                'employee_id' => $employee->id,
                'compensation_policy_id' => $p->id,
                'effective_from' => '2026-01-01',
                'active' => true,
            ]);
        }

        return $period;
    }

    public function test_full_flow_draft_finalize_publish_employee_visibility(): void
    {
        $employee = $this->makeEmployee();
        $officer = $this->makeUserWithRole(Role::PAYROLL_OFFICER);

        $period = $this->seedWorkData($employee);

        $run = PayrollRun::create(['payroll_period_id' => $period->id, 'status' => 'draft']);

        // 1. Generate draft.
        $this->authPost($officer, "/api/v1/admin/payroll/runs/{$run->id}/generate")->assertOk();

        $item = $run->items()->where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame(2000000, (int) $item->base_salary);
        $this->assertSame(1600000, (int) $item->attendance_comp); // 80.000 x 20
        $this->assertSame(3600000, (int) $item->net);
        $this->assertSame(20, (int) $item->work_days);
        $this->assertSame('draft', $run->fresh()->status);

        // 2. Review -> finalize (snapshots rates, creates payslips).
        $this->authPost($officer, "/api/v1/admin/payroll/runs/{$run->id}/review")->assertOk();
        $this->authPost($officer, "/api/v1/admin/payroll/runs/{$run->id}/finalize")->assertOk();

        $run->refresh();
        $this->assertSame('finalized', $run->status);
        $this->assertNotNull($run->finalized_at);
        $this->assertNotNull($run->rate_snapshot);

        $slip = Payslip::whereHas('item', fn ($q) => $q->where('payroll_run_id', $run->id))->firstOrFail();
        $this->assertFalse((bool) $slip->published);
        $this->assertNotEmpty($slip->reference_code);

        // Employee cannot see the payslip before publish.
        $this->authGet($employee->user, '/api/v1/payslips')
            ->assertOk()
            ->assertJsonCount(0, 'items');

        // 3. Publish -> visible to employee.
        $this->authPost($officer, "/api/v1/admin/payroll/runs/{$run->id}/publish")->assertOk();

        $list = $this->authGet($employee->user, '/api/v1/payslips');
        $list->assertOk()->assertJsonCount(1, 'items');
        $this->assertSame(3600000, $list->json('items.0.total_diterima'));

        // 4. Finalized run cannot be finalized again.
        $this->authPost($officer, "/api/v1/admin/payroll/runs/{$run->id}/finalize")
            ->assertStatus(403); // policy: finalize requires editable run
    }

    public function test_payslip_pdf_download_via_signed_url(): void
    {
        $employee = $this->makeEmployee();
        $officer = $this->makeUserWithRole(Role::PAYROLL_OFFICER);

        $period = $this->seedWorkData($employee);
        $run = PayrollRun::create(['payroll_period_id' => $period->id, 'status' => 'review']);

        $this->authPost($officer, "/api/v1/admin/payroll/runs/{$run->id}/generate")->assertOk();
        $this->authPost($officer, "/api/v1/admin/payroll/runs/{$run->id}/finalize")->assertOk();
        $this->authPost($officer, "/api/v1/admin/payroll/runs/{$run->id}/publish")->assertOk();

        $slip = Payslip::whereHas('item', fn ($q) => $q->where('payroll_run_id', $run->id))->firstOrFail();

        $show = $this->authGet($employee->user, "/api/v1/payslips/{$slip->id}")->assertOk();
        // PayslipDetail contract fields.
        $show->assertJsonStructure([
            'id', 'periode', 'tanggal_mulai', 'tanggal_selesai', 'total_diterima',
            'status_bayar', 'nomor_dokumen', 'gaji_pokok', 'insentif_kehadiran',
            'lembur', 'tunjangan', 'potongan', 'reimburse_disetujui', 'rincian',
        ]);

        // download-url endpoint returns the signed URL + expiry.
        $dl = $this->authGet($employee->user, "/api/v1/payslips/{$slip->id}/download-url")
            ->assertOk()
            ->assertJsonStructure(['url', 'expires_at']);
        $this->assertStringContainsString('signature=', $dl->json('url'));
        $url = $dl->json('url');

        // Signed URL downloads a real PDF.
        $this->refreshAuth();
        $pdf = $this->get($url, $this->authHeaders($employee->user));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        // Tampered signature is rejected.
        $this->refreshAuth();
        $this->get($url . 'tampered', $this->authHeaders($employee->user))->assertStatus(403);
    }
}
