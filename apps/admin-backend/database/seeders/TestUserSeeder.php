<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('TEST_USER_PASSWORD', 'agus1234');

        $user = User::updateOrCreate(
            ['email' => 'agus@example.com'],
            [
                'name' => 'Agus',
                'phone' => '081234567890',
                'password' => Hash::make($password),
            ]
        );

        $role = Role::where('name', Role::EMPLOYEE)->first();
        if ($role) {
            $user->roles()->syncWithoutDetaching([$role->id]);
        }

        Employee::updateOrCreate(
            ['employee_code' => 'EMP-001'],
            [
                'user_id' => $user->id,
                'name' => 'Agus',
                'phone' => '081234567890',
                'email' => 'agus@example.com',
                'position' => 'Staff',
                'employment_status' => 'aktif',
                'hire_date' => now()->toDateString(),
                'notes' => 'Akun uji otomatis (TestUserSeeder).',
            ]
        );

        $this->command->info('Test user: Agus <agus@example.com> / EMP-001 (password dari TEST_USER_PASSWORD, default: agus1234)');
    }
}
