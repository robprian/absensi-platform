<?php

namespace Tests\Feature;

use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\MakesTestUsers;

class RbacTest extends TestCase
{
    use RefreshDatabase, MakesTestUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBase();
    }

    protected function makeRun(): PayrollRun
    {
        $period = PayrollPeriod::create([
            'name' => 'Oktober 2026', 'period_type' => 'monthly',
            'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31', 'status' => 'open',
        ]);
        return PayrollRun::create(['payroll_period_id' => $period->id, 'status' => 'review']);
    }

    public function test_unauthenticated_cannot_access_api(): void
    {
        $this->postJson('/api/v1/attendance/clock-in', [])->assertStatus(401);
        $this->getJson('/api/v1/attendance/today')->assertStatus(401);
    }

    public function test_employee_cannot_finalize_payroll(): void
    {
        $employee = $this->makeEmployee();
        $run = $this->makeRun();

        $this->authPost($employee->user, "/api/v1/admin/payroll/runs/{$run->id}/finalize")
            ->assertStatus(403);

        $this->assertSame('review', $run->fresh()->status);
    }

    public function test_employee_cannot_access_admin_employee_list(): void
    {
        $employee = $this->makeEmployee();

        $this->authGet($employee->user, '/api/v1/admin/employees')
            ->assertStatus(403);
    }

    public function test_payroll_officer_can_finalize_but_not_lock(): void
    {
        $officer = $this->makeUserWithRole(Role::PAYROLL_OFFICER);
        $run = $this->makeRun();

        $this->fakeTdocs();
        $this->authPost($officer, "/api/v1/admin/payroll/runs/{$run->id}/finalize")
            ->assertStatus(200);
        $this->assertSame('finalized', $run->fresh()->status);

        // payroll_officer lacks the payroll.lock permission -> policy denies.
        $this->authPost($officer, "/api/v1/admin/payroll/runs/{$run->id}/lock")
            ->assertStatus(403);
        $this->assertSame('finalized', $run->fresh()->status);
    }

    public function test_hr_admin_cannot_lock_but_super_admin_can(): void
    {
        $hr = $this->makeUserWithRole(Role::HR_ADMIN);
        $super = $this->makeUserWithRole(Role::SUPER_ADMIN);

        $run = $this->makeRun();
        $run->update(['status' => 'finalized']);

        $this->authPost($hr, "/api/v1/admin/payroll/runs/{$run->id}/lock")
            ->assertStatus(403);

        $this->authPost($super, "/api/v1/admin/payroll/runs/{$run->id}/lock")
            ->assertStatus(200);
        $this->assertSame('locked', $run->fresh()->status);
    }

    public function test_employee_cannot_view_other_employees_payslip(): void
    {
        $empA = $this->makeEmployee();
        $empB = $this->makeEmployee();

        $run = $this->makeRun();
        $run->update(['status' => 'finalized']);
        $item = PayrollItem::create([
            'payroll_run_id' => $run->id, 'employee_id' => $empB->id,
            'net' => 2700000, 'gross' => 2700000, 'status' => 'finalized',
        ]);
        $slip = Payslip::create([
            'payroll_item_id' => $item->id,
            'reference_code' => 'SLIP-X-1',
            'published' => true, 'published_at' => now(),
        ]);

        // empA tries to open empB's slip -> 404 (not leaked).
        $this->authGet($empA->user, "/api/v1/payslips/{$slip->id}")
            ->assertStatus(404);
    }
}
