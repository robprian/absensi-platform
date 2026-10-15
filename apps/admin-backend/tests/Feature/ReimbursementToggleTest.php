<?php

namespace Tests\Feature;

use App\Models\Reimbursement;
use App\Models\Role;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use Tests\Traits\MakesTestUsers;

class ReimbursementToggleTest extends TestCase
{
    use RefreshDatabase, MakesTestUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBase();
        $this->fakeTdocs();
    }

    protected function claimPayload(string $key): array
    {
        return [
            'category' => 'Transportasi',
            'amount' => 35000,
            'expense_date' => '2026-10-14',
            'description' => 'Ongkos menuju lokasi kerja',
            'receipt' => UploadedFile::fake()->image('receipt.jpg', 640, 480),
            'idempotency_key' => $key,
        ];
    }

    public function test_employee_submit_blocked_when_disabled(): void
    {
        SystemSetting::set('reimbursement_enabled', false, 'boolean', 'reimbursement');
        $employee = $this->makeEmployee();

        $this->authPost($employee->user, '/api/v1/reimbursements', $this->claimPayload('r-1'))
            ->assertStatus(403)
            ->assertJsonPath('code', 'reimbursement_disabled');

        $this->assertSame(0, Reimbursement::count());
    }

    public function test_employee_submit_works_when_enabled(): void
    {
        SystemSetting::set('reimbursement_enabled', true, 'boolean', 'reimbursement');
        $employee = $this->makeEmployee();

        $res = $this->authPost($employee->user, '/api/v1/reimbursements', $this->claimPayload('r-2'));
        $res->assertStatus(201);
        $res->assertJsonPath('ok', true);
        $this->assertNotEmpty($res->json('id'));
        $this->assertSame('menunggu', Reimbursement::find($res->json('id'))->status);
    }

    public function test_admin_can_review_history_while_disabled(): void
    {
        $employee = $this->makeEmployee();
        $claim = Reimbursement::create([
            'employee_id' => $employee->id,
            'category' => 'Transportasi',
            'amount' => 35000,
            'expense_date' => '2026-10-14',
            'status' => 'menunggu',
        ]);

        SystemSetting::set('reimbursement_enabled', false, 'boolean', 'reimbursement');
        $admin = $this->makeUserWithRole(Role::HR_ADMIN);

        // Admin history stays accessible.
        $this->authGet($admin, '/api/v1/admin/reimbursements')
            ->assertOk()
            ->assertJsonFragment(['id' => $claim->id]);

        // Admin can still approve while the employee feature is off.
        $this->authPost($admin, "/api/v1/admin/reimbursements/{$claim->id}/transition", ['status' => 'disetujui'])
            ->assertOk();

        $this->assertSame('disetujui', $claim->fresh()->status);
    }

    public function test_invalid_transition_rejected(): void
    {
        $employee = $this->makeEmployee();
        $claim = Reimbursement::create([
            'employee_id' => $employee->id,
            'category' => 'Transportasi',
            'amount' => 35000,
            'expense_date' => '2026-10-14',
            'status' => 'menunggu',
        ]);
        $admin = $this->makeUserWithRole(Role::HR_ADMIN);

        // Cannot jump straight to dibayar.
        $this->authPost($admin, "/api/v1/admin/reimbursements/{$claim->id}/transition", ['status' => 'dibayar'])
            ->assertStatus(422);

        // disetujui -> dibayar is valid.
        $this->authPost($admin, "/api/v1/admin/reimbursements/{$claim->id}/transition", ['status' => 'disetujui'])
            ->assertOk();
        $this->authPost($admin, "/api/v1/admin/reimbursements/{$claim->id}/transition", ['status' => 'dibayar'])
            ->assertOk();

        $claim->refresh();
        $this->assertSame('dibayar', $claim->status);
        $this->assertNotNull($claim->paid_at);

        // No duplicate payout: dibayar is terminal.
        $this->authPost($admin, "/api/v1/admin/reimbursements/{$claim->id}/transition", ['status' => 'dibayar'])
            ->assertStatus(422);
    }
}
