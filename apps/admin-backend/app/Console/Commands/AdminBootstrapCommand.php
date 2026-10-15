<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class AdminBootstrapCommand extends Command
{
    protected $signature = 'admin:bootstrap
        {--email=robby@robrion.net : Email akun admin}
        {--name=Robby : Nama tampilan admin}
        {--password= : Kata sandi (default: dibuat acak 8 karakter)}';

    protected $description = 'Buat/reset akun super-admin. Kata sandi dibuat acak 8 karakter bila tidak diberikan.';

    public function handle(): int
    {
        $email = (string) $this->option('email');
        $name = (string) $this->option('name');
        $password = (string) ($this->option('password') ?: self::randomPassword(8));

        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make($password)]
        );

        $role = Role::where('name', Role::SUPER_ADMIN)->first();
        if (!$role) {
            $this->error('Role super-admin belum ada. Jalankan: php artisan db:seed --class=RolePermissionSeeder');
            return self::FAILURE;
        }
        $user->roles()->syncWithoutDetaching([$role->id]);

        $this->info('Super-admin siap.');
        $this->line("  Email    : {$email}");
        $this->line("  Password : {$password}");
        $this->warn('Simpan kredensial ini — tidak ditampilkan lagi.');

        return self::SUCCESS;
    }

    /** Kata sandi acak 8 karakter alfanumerik (tanpa karakter ambigu). */
    public static function randomPassword(int $length = 8): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $password = '';
        $max = strlen($alphabet) - 1;
        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, $max)];
        }
        return $password;
    }
}
