<?php

namespace Tests\Feature;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\MakesTestUsers;

class AdminWebTest extends TestCase
{
    use RefreshDatabase, MakesTestUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBase();
    }

    public function test_admin_login_and_dashboard_render(): void
    {
        $admin = $this->makeUserWithRole(Role::SUPER_ADMIN, ['email' => 'boss@example.com', 'password' => bcrypt('secret123')]);

        // Login page renders.
        $this->get('/admin/login')->assertOk();

        // Wrong password rejected.
        $this->post('/admin/login', ['email' => 'boss@example.com', 'password' => 'wrong'])
            ->assertSessionHasErrors('email');

        // Correct login -> dashboard.
        $res = $this->post('/admin/login', ['email' => 'boss@example.com', 'password' => 'secret123']);
        $res->assertRedirect(route('admin.dashboard'));

        $this->get('/admin')->assertOk()->assertSee('Dashboard');
        $this->get('/admin/karyawan')->assertOk()->assertSee('Karyawan');
        $this->get('/admin/absensi')->assertOk();
        $this->get('/admin/jadwal')->assertOk();
        $this->get('/admin/penggajian')->assertOk();
        $this->get('/admin/reimburse')->assertOk();
        $this->get('/admin/laporan')->assertOk();
        $this->get('/admin/dokumen')->assertOk();
        $this->get('/admin/pengaturan')->assertOk()->assertSee('reimbursement_enabled');
        $this->get('/admin/audit-log')->assertOk();
    }

    public function test_employee_cannot_access_admin_web(): void
    {
        $employee = $this->makeEmployee();
        $this->actingAs($employee->user);

        $this->get('/admin')->assertForbidden();
        $this->get('/admin/karyawan')->assertForbidden();
    }

    public function test_guest_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }
}
