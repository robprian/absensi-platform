<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'phone', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ------------------------------------------------------------------
    // RBAC
    // ------------------------------------------------------------------

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'employee_role_assignments');
    }

    /** @var string[]|null */
    private ?array $roleCache = null;

    /** @return string[] */
    public function roleNames(): array
    {
        if ($this->roleCache === null) {
            $this->roleCache = $this->roles()->pluck('roles.name')->all();
        }
        return $this->roleCache;
    }

    public function hasRole(string ...$names): bool
    {
        return (bool) array_intersect($names, $this->roleNames());
    }

    public function hasAnyRole(array $names): bool
    {
        return $this->hasRole(...$names);
    }

    public function isAdmin(): bool
    {
        return $this->hasAnyRole(['super_admin', 'hr_admin', 'payroll_officer', 'supervisor']);
    }

    public function hasPermission(string $permission): bool
    {
        return $this->roles()
            ->whereHas('permissions', fn ($q) => $q->where('permissions.name', $permission))
            ->exists();
    }

    // ------------------------------------------------------------------
    // Relations
    // ------------------------------------------------------------------

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }
}
