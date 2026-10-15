<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeCompensationRule extends Model
{
    protected $fillable = [
        'employee_id', 'compensation_policy_id', 'custom_amount',
        'effective_from', 'effective_until', 'active',
    ];

    protected function casts(): array
    {
        return [
            'custom_amount' => 'integer',
            'effective_from' => 'date',
            'effective_until' => 'date',
            'active' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(CompensationPolicy::class, 'compensation_policy_id');
    }

    public function effectiveAmount(): int
    {
        return (int) ($this->custom_amount ?? $this->policy->amount);
    }
}
