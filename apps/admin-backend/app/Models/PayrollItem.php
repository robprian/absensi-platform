<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PayrollItem extends Model
{
    protected $fillable = [
        'payroll_run_id', 'employee_id', 'base_salary', 'attendance_comp', 'overtime',
        'allowances', 'bonuses', 'reimbursements', 'deductions', 'gross', 'net',
        'work_days', 'total_minutes', 'rate_snapshot', 'status',
    ];

    protected function casts(): array
    {
        return ['rate_snapshot' => 'array'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payslip(): HasOne
    {
        return $this->hasOne(Payslip::class);
    }
}
