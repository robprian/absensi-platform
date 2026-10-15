<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@example.com');
        $password = env('ADMIN_PASSWORD', 'changeme123');

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'Administrator'),
                'password' => Hash::make($password),
            ]
        );

        $role = Role::where('name', Role::SUPER_ADMIN)->firstOrFail();
        $user->roles()->syncWithoutDetaching([$role->id]);

        $this->command->warn("Super admin: {$email} (set ADMIN_EMAIL/ADMIN_PASSWORD in .env)");
    }
}
