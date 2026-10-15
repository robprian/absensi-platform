<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompensationPolicy extends Model
{
    public const TYPE_PER_ATTENDANCE = 'per_attendance';
    public const TYPE_HOURLY = 'hourly';
    public const TYPE_DAILY = 'daily';
    public const TYPE_MONTHLY_BASE = 'monthly_base';
    public const TYPE_OVERTIME = 'overtime';
    public const TYPE_ALLOWANCE = 'allowance';
    public const TYPE_BONUS = 'bonus';
    public const TYPE_DEDUCTION = 'deduction';

    protected $fillable = ['name', 'policy_type', 'amount', 'applies_when', 'priority', 'active'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'applies_when' => 'array', 'active' => 'boolean'];
    }

    public function rules(): HasMany
    {
        return $this->hasMany(EmployeeCompensationRule::class);
    }
}
