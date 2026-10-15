<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'employees.view' => 'Lihat data karyawan',
            'employees.manage' => 'Kelola karyawan (tambah/ubah/nonaktifkan)',
            'attendance.view' => 'Lihat data absensi',
            'attendance.review' => 'Review & koreksi absensi',
            'schedules.manage' => 'Kelola jadwal kerja',
            'payroll.configure' => 'Konfigurasi aturan penggajian',
            'payroll.generate' => 'Buat draf penggajian',
            'payroll.finalize' => 'Finalisasi penggajian',
            'payroll.publish' => 'Publikasikan slip gaji',
            'payroll.lock' => 'Kunci periode penggajian',
            'reimbursements.review' => 'Setujui/tolak/bayar reimburse',
            'reports.view' => 'Lihat laporan',
            'documents.view' => 'Lihat dokumen',
            'settings.manage' => 'Kelola pengaturan aplikasi',
            'audit.view' => 'Lihat audit log',
        ];

        foreach ($permissions as $name => $label) {
            Permission::firstOrCreate(['name' => $name], ['label' => $label]);
        }

        $roles = [
            Role::SUPER_ADMIN => 'Super Admin',
            Role::HR_ADMIN => 'HR / Admin',
            Role::PAYROLL_OFFICER => 'Petugas Payroll',
            Role::SUPERVISOR => 'Supervisor',
            Role::EMPLOYEE => 'Karyawan',
        ];
        foreach ($roles as $name => $label) {
            Role::firstOrCreate(['name' => $name], ['label' => $label]);
        }

        $grant = function (string $role, array $perms) {
            $r = Role::where('name', $role)->firstOrFail();
            $ids = Permission::whereIn('name', $perms)->pluck('id');
            $r->permissions()->sync($ids);
        };

        $all = array_keys($permissions);

        $grant(Role::SUPER_ADMIN, $all);
        $grant(Role::HR_ADMIN, array_diff($all, ['settings.manage', 'payroll.lock']));
        $grant(Role::PAYROLL_OFFICER, [
            'payroll.configure', 'payroll.generate', 'payroll.finalize', 'payroll.publish',
            'reimbursements.review', 'reports.view', 'attendance.view', 'documents.view',
            'employees.view',
        ]);
        $grant(Role::SUPERVISOR, [
            'attendance.view', 'attendance.review', 'reports.view', 'employees.view', 'documents.view',
        ]);
        $grant(Role::EMPLOYEE, []); // employees access their own data via dedicated API policies
    }
}
