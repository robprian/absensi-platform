<?php

namespace Tests\Feature;

use App\Models\AttendanceEvent;
use App\Models\AttendanceSession;
use App\Models\Notification;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\Reimbursement;
use App\Models\SystemSetting;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\MakesTestUsers;

/**
 * PWA API contract: every endpoint the SvelteKit employee app consumes
 * must match the shapes in apps/employee-pwa/src/lib/api.ts.
 */
class EmployeeContractTest extends TestCase
{
    use RefreshDatabase, MakesTestUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBase();
    }

    // ------------------------------------------------------------------
    // Auth
    // ------------------------------------------------------------------

    public function test_login_accepts_email_phone_and_employee_code(): void
    {
        $employee = $this->makeEmployee([
            'employee_code' => 'EMP-777',
            'phone' => '081234567890',
            'email' => 'budi@example.com',
            'name' => 'Budi Santoso',
            'position' => 'Kurir',
        ]);
        $userEmail = $employee->user->email;

        foreach (['budi@example.com', '081234567890', 'EMP-777', $userEmail] as $identifier) {
            $res = $this->postJson('/api/v1/auth/login', [
                'identifier' => $identifier,
                'password' => 'password123',
            ]);
            $res->assertOk();
            $this->assertNotEmpty($res->json('token'));
            $emp = $res->json('employee');
            $this->assertSame('Budi Santoso', $emp['nama']);
            $this->assertSame('EMP-777', $emp['employee_id']);
            $this->assertIsString($emp['id']);
            $this->assertSame('081234567890', $emp['telepon']);
            $this->assertSame('Kurir', $emp['jabatan']);
            $this->assertSame('aktif', $emp['status']);
            $this->assertArrayHasKey('departemen', $emp);
            $this->assertArrayHasKey('lokasi_kerja', $emp);
        }
    }

    public function test_login_rejects_wrong_password(): void
    {
        $this->makeEmployee(['employee_code' => 'EMP-778']);
        $this->postJson('/api/v1/auth/login', [
            'identifier' => 'EMP-778',
            'password' => 'salah',
        ])->assertStatus(422);
    }

    public function test_logout_and_me_shapes(): void
    {
        $employee = $this->makeEmployee(['name' => 'Siti']);

        $this->authPost($employee->user, '/api/v1/auth/logout')
            ->assertOk()
            ->assertJson(['ok' => true]);

        // Token was revoked by logout; use a fresh one for /me.
        $me = $this->authGet($employee->user, '/api/v1/auth/me')->assertOk();
        $this->assertSame('Siti', $me->json('employee.nama'));
        $this->assertIsString($me->json('employee.id'));
    }

    // ------------------------------------------------------------------
    // Attendance: today + clock in/out (PWA field names)
    // ------------------------------------------------------------------

    protected function pwaClockPayload(string $key): array
    {
        return [
            'photo' => UploadedFile::fake()->image('absen-masuk.jpg', 640, 480),
            'type' => 'MASUK',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'accuracy' => 8,
            'captured_at' => now()->toIso8601String(),
        ];
    }

    public function test_attendance_today_clockin_clockout_flow(): void
    {
        $this->fakeTdocs();
        $employee = $this->makeEmployee();

        // Before clock-in.
        $today = $this->authGet($employee->user, '/api/v1/attendance/today')->assertOk();
        $this->assertSame('belum_absen', $today->json('status'));
        $this->assertNull($today->json('masuk_at'));
        $this->assertFalse($today->json('ada_antrean'));

        // Clock-in with the exact PWA field names.
        $in = $this->authPost(
            $employee->user,
            '/api/v1/attendance/clock-in',
            $this->pwaClockPayload('pwa-in-1'),
            ['Idempotency-Key' => 'pwa-in-1']
        );
        $in->assertStatus(201)
            ->assertJsonPath('ok', true);
        $masukAt = $in->json('masuk_at');
        $this->assertNotEmpty($masukAt);
        // ISO 8601 parseable by the PWA's new Date().
        $this->assertNotFalse(strtotime($masukAt));

        $today = $this->authGet($employee->user, '/api/v1/attendance/today')->assertOk();
        $this->assertSame('sedang_bekerja', $today->json('status'));
        $this->assertSame($masukAt, $today->json('masuk_at'));

        // Clock-out.
        $out = $this->authPost(
            $employee->user,
            '/api/v1/attendance/clock-out',
            $this->pwaClockPayload('pwa-out-1'),
            ['Idempotency-Key' => 'pwa-out-1']
        );
        $out->assertStatus(201)
            ->assertJsonPath('ok', true);
        $this->assertNotEmpty($out->json('keluar_at'));
        $this->assertIsInt($out->json('total_menit'));

        $today = $this->authGet($employee->user, '/api/v1/attendance/today')->assertOk();
        $this->assertSame('selesai_bekerja', $today->json('status'));
        $this->assertNotEmpty($today->json('keluar_at'));
        $this->assertIsInt($today->json('total_menit'));
    }

    // ------------------------------------------------------------------
    // Geocode (best-effort)
    // ------------------------------------------------------------------

    public function test_geocode_returns_address_on_success_and_null_on_failure(): void
    {
        $employee = $this->makeEmployee();

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                'display_name' => 'Jl. Sudirman, Jakarta Selatan',
            ], 200),
        ]);
        $res = $this->authPost($employee->user, '/api/v1/attendance/geocode', [
            'latitude' => -6.2088, 'longitude' => 106.8456,
        ]);
        $res->assertOk()->assertJsonPath('address', 'Jl. Sudirman, Jakarta Selatan');

        // ANY failure (timeout/network) -> {address: null}, never 500.
        Http::fake([
            'nominatim.openstreetmap.org/*' => function () {
                throw new \RuntimeException('timeout');
            },
        ]);
        $res = $this->authPost($employee->user, '/api/v1/attendance/geocode', [
            'latitude' => -6.2088, 'longitude' => 106.8456,
        ]);
        $res->assertOk()->assertJson(['address' => null]);
    }

    // ------------------------------------------------------------------
    // History
    // ------------------------------------------------------------------

    protected function seedSession($employee, string $date, bool $withKeluar): AttendanceSession
    {
        $session = AttendanceSession::create([
            'employee_id' => $employee->id,
            'work_date' => $date,
            'status' => $withKeluar ? 'closed' : 'open',
            'total_minutes' => $withKeluar ? 480 : null,
        ]);
        $in = CarbonImmutable::parse("$date 08:00:00", 'Asia/Jakarta')->utc();
        AttendanceEvent::create([
            'attendance_session_id' => $session->id,
            'employee_id' => $employee->id,
            'event_type' => 'MASUK',
            'server_at' => $in,
            'latitude' => -6.2088, 'longitude' => 106.8456, 'accuracy_meters' => 8,
            'address' => 'Jl. Sudirman, Jakarta Selatan',
            'idempotency_key' => "h-in-$date",
        ]);
        if ($withKeluar) {
            AttendanceEvent::create([
                'attendance_session_id' => $session->id,
                'employee_id' => $employee->id,
                'event_type' => 'KELUAR',
                'server_at' => $in->addHours(9),
                'latitude' => -6.2088, 'longitude' => 106.8456, 'accuracy_meters' => 8,
                'idempotency_key' => "h-out-$date",
            ]);
        }
        return $session;
    }

    public function test_history_bulan_filter_and_status_mapping(): void
    {
        $employee = $this->makeEmployee();

        $this->seedSession($employee, '2026-10-05', true);   // complete -> hadir
        $this->seedSession($employee, '2026-10-06', false);  // open -> tidak_lengkap
        $this->seedSession($employee, '2026-09-20', true);   // other month

        $res = $this->authGet($employee->user, '/api/v1/attendance/history?bulan=2026-10')
            ->assertOk();
        $items = $res->json('items');
        $this->assertCount(2, $items);

        $byTanggal = [];
        foreach ($items as $it) {
            $byTanggal[$it['tanggal']] = $it;
            $this->assertIsString($it['id']);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $it['tanggal']);
            $this->assertContains($it['status'], ['hadir', 'terlambat', 'pulang_cepat', 'lembur', 'tidak_lengkap', 'libur']);
            $this->assertArrayHasKey('lokasi', $it);
        }

        $this->assertSame('hadir', $byTanggal['2026-10-05']['status']);
        $this->assertNotEmpty($byTanggal['2026-10-05']['masuk_at']);
        $this->assertNotEmpty($byTanggal['2026-10-05']['keluar_at']);
        $this->assertSame(480, $byTanggal['2026-10-05']['total_menit']);
        $this->assertSame('Jl. Sudirman, Jakarta Selatan', $byTanggal['2026-10-05']['lokasi']);

        $this->assertSame('tidak_lengkap', $byTanggal['2026-10-06']['status']);
        $this->assertNull($byTanggal['2026-10-06']['keluar_at']);

        // September item only visible with its own filter.
        $sep = $this->authGet($employee->user, '/api/v1/attendance/history?bulan=2026-09')
            ->assertOk()->json('items');
        $this->assertCount(1, $sep);
        $this->assertSame('2026-09-20', $sep[0]['tanggal']);
    }

    public function test_history_rejects_bad_bulan(): void
    {
        $employee = $this->makeEmployee();
        $this->authGet($employee->user, '/api/v1/attendance/history?bulan=oktober')
            ->assertStatus(422);
    }

    // ------------------------------------------------------------------
    // Payslips
    // ------------------------------------------------------------------

    protected function seedPublishedPayslip($employee, string $runStatus = 'finalized'): Payslip
    {
        $period = PayrollPeriod::create([
            'name' => 'September 2026', 'period_type' => 'monthly',
            'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30', 'status' => 'open',
        ]);
        $run = PayrollRun::create(['payroll_period_id' => $period->id, 'status' => $runStatus]);
        $item = PayrollItem::create([
            'payroll_run_id' => $run->id, 'employee_id' => $employee->id,
            'base_salary' => 2000000, 'attendance_comp' => 400000, 'overtime' => 200000,
            'allowances' => 150000, 'bonuses' => 0, 'reimbursements' => 50000,
            'deductions' => 50000, 'gross' => 2750000, 'net' => 2700000,
            'work_days' => 20, 'status' => $runStatus,
        ]);
        return Payslip::create([
            'payroll_item_id' => $item->id,
            'reference_code' => 'SLIP-202609-1-0001',
            'published' => true, 'published_at' => now(),
        ]);
    }

    public function test_payslips_index_finalized_only_and_summary_shape(): void
    {
        $employee = $this->makeEmployee();
        $this->seedPublishedPayslip($employee);

        $res = $this->authGet($employee->user, '/api/v1/payslips')->assertOk();
        $items = $res->json('items');
        $this->assertCount(1, $items);
        $s = $items[0];
        $this->assertIsString($s['id']);
        $this->assertSame('September 2026', $s['periode']);
        $this->assertSame('2026-09-01', $s['tanggal_mulai']);
        $this->assertSame('2026-09-30', $s['tanggal_selesai']);
        $this->assertSame(2700000, $s['total_diterima']);
        $this->assertSame('belum', $s['status_bayar']);
    }

    public function test_payslip_detail_and_download_url(): void
    {
        $employee = $this->makeEmployee();
        $slip = $this->seedPublishedPayslip($employee, 'locked');

        $res = $this->authGet($employee->user, "/api/v1/payslips/{$slip->id}")->assertOk();
        $d = $res->json();
        $this->assertSame('September 2026', $d['periode']);
        $this->assertSame('SLIP-202609-1-0001', $d['nomor_dokumen']);
        $this->assertSame(2000000, $d['gaji_pokok']);
        $this->assertSame(400000, $d['insentif_kehadiran']);
        $this->assertSame(200000, $d['lembur']);
        $this->assertSame(150000, $d['tunjangan']);
        $this->assertSame(50000, $d['potongan']);
        $this->assertSame(50000, $d['reimburse_disetujui']);
        $this->assertSame(2700000, $d['total_diterima']);
        $this->assertSame('dibayar', $d['status_bayar']);
        $this->assertIsArray($d['rincian']);
        foreach ($d['rincian'] as $r) {
            $this->assertArrayHasKey('label', $r);
            $this->assertArrayHasKey('nominal', $r);
            $this->assertContains($r['tipe'], ['tambah', 'kurang']);
        }

        $dl = $this->authGet($employee->user, "/api/v1/payslips/{$slip->id}/download-url")
            ->assertOk();
        $this->assertStringContainsString('signature=', $dl->json('url'));
        $this->assertNotFalse(strtotime($dl->json('expires_at')));
    }

    public function test_payslips_hidden_before_publish(): void
    {
        $employee = $this->makeEmployee();
        $slip = $this->seedPublishedPayslip($employee);
        $slip->update(['published' => false]);

        $res = $this->authGet($employee->user, '/api/v1/payslips')->assertOk();
        $this->assertCount(0, $res->json('items'));
        $this->authGet($employee->user, "/api/v1/payslips/{$slip->id}")->assertStatus(404);
    }

    // ------------------------------------------------------------------
    // Reimbursements
    // ------------------------------------------------------------------

    public function test_reimbursements_index_shape(): void
    {
        SystemSetting::set('reimbursement_enabled', true, 'boolean', 'reimbursement');
        $employee = $this->makeEmployee();
        Reimbursement::create([
            'employee_id' => $employee->id, 'category' => 'Transportasi',
            'amount' => 35000, 'expense_date' => '2026-10-14',
            'description' => 'Ongkos', 'status' => 'menunggu',
        ]);

        $res = $this->authGet($employee->user, '/api/v1/reimbursements')->assertOk();
        $items = $res->json('items');
        $this->assertCount(1, $items);
        $r = $items[0];
        $this->assertIsString($r['id']);
        $this->assertSame('Transportasi', $r['kategori']);
        $this->assertSame(35000, $r['nominal']);
        $this->assertSame('2026-10-14', $r['tanggal']);
        $this->assertSame('Ongkos', $r['keterangan']);
        $this->assertSame('menunggu', $r['status']);
        $this->assertFalse($r['ada_bukti']);
        $this->assertNotEmpty($r['dibuat_at']);
    }

    public function test_reimbursement_store_accepts_pwa_field_names(): void
    {
        SystemSetting::set('reimbursement_enabled', true, 'boolean', 'reimbursement');
        $this->fakeTdocs();
        $employee = $this->makeEmployee();

        $res = $this->authPost(
            $employee->user,
            '/api/v1/reimbursements',
            [
                'kategori' => 'Transportasi',
                'nominal' => 35000,
                'tanggal' => '2026-10-14',
                'keterangan' => 'Ongkos menuju lokasi kerja',
                'bukti' => UploadedFile::fake()->image('receipt.jpg', 640, 480),
            ],
            ['Idempotency-Key' => 'rmb-pwa-1']
        );
        $res->assertStatus(201)
            ->assertJsonPath('ok', true);
        $this->assertNotEmpty($res->json('id'));

        $claim = Reimbursement::findOrFail($res->json('id'));
        $this->assertSame('Transportasi', $claim->category);
        $this->assertSame(35000, (int) $claim->amount);
    }

    // ------------------------------------------------------------------
    // Settings + notifications
    // ------------------------------------------------------------------

    public function test_public_settings_shape(): void
    {
        $employee = $this->makeEmployee();
        $res = $this->authGet($employee->user, '/api/v1/settings/public')->assertOk();
        $this->assertIsBool($res->json('reimbursement_enabled'));
        $this->assertIsString($res->json('nama_perusahaan'));
    }

    public function test_notifications_index_and_read(): void
    {
        $employee = $this->makeEmployee();
        $this->assertCount(
            0,
            $this->authGet($employee->user, '/api/v1/notifications')->assertOk()->json('items')
        );

        $n = Notification::create([
            'user_id' => $employee->user->id,
            'title' => 'Slip gaji tersedia',
            'body' => 'Slip gaji September 2026 sudah diterbitkan.',
        ]);

        $items = $this->authGet($employee->user, '/api/v1/notifications')
            ->assertOk()->json('items');
        $this->assertCount(1, $items);
        $this->assertSame((string) $n->id, $items[0]['id']);
        $this->assertSame('Slip gaji tersedia', $items[0]['judul']);
        $this->assertSame('Slip gaji September 2026 sudah diterbitkan.', $items[0]['isi']);
        $this->assertFalse($items[0]['sudah_dibaca']);
        $this->assertNotEmpty($items[0]['dibuat_at']);

        $this->authPost($employee->user, "/api/v1/notifications/{$n->id}/read")
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertTrue(
            $this->authGet($employee->user, '/api/v1/notifications')->assertOk()->json('items.0.sudah_dibaca')
        );
    }
}
