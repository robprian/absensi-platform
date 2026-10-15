<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\Reimbursement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Payroll engine. ALL money is integer IDR — no floats anywhere.
 *
 * Compensation models (per compensation_policies.policy_type):
 *  - per_attendance : fixed amount per eligible workday
 *  - hourly         : amount per hour, prorated per minute: intdiv(amount * minutes, 60)
 *  - daily          : fixed amount per workday
 *  - monthly_base   : full amount for monthly periods, prorated by days/30 otherwise
 *  - overtime       : amount per overtime hour, prorated per minute
 *  - allowance      : flat per period (requires >= 1 workday unless applies_when says otherwise)
 *  - bonus          : flat per period
 *  - deduction      : flat per period, or per late day when applies_when.per_late_day is true
 *
 * Eligibility (applies_when json): {"min_minutes":480,"requires_clock_out":true}
 * Rate snapshots are frozen at finalization; employees see only finalized payslips.
 */
class PayrollService
{
    /**
     * @param array<int, array{work_date: string, total_minutes: int, overtime_minutes: int, is_late: bool, has_clock_out: bool}> $sessions
     * @return array<string,int> item fields (integer rupiah)
     */
    public function calculateItem(Employee $employee, PayrollPeriod $period, array $sessions): array
    {
        $workDays = count($sessions);
        $totalMinutes = array_sum(array_column($sessions, 'total_minutes'));
        $overtimeMinutes = array_sum(array_column($sessions, 'overtime_minutes'));
        $lateDays = count(array_filter($sessions, fn ($s) => !empty($s['is_late'])));

        $rules = $employee->compensationRules()
            ->with('policy')
            ->where('active', true)
            ->where('effective_from', '<=', $period->ends_on->format('Y-m-d'))
            ->where(fn ($q) => $q->whereNull('effective_until')
                ->orWhere('effective_until', '>=', $period->starts_on->format('Y-m-d')))
            ->get();

        $snapshot = [];
        $totals = [
            'base_salary' => 0, 'attendance_comp' => 0, 'overtime' => 0,
            'allowances' => 0, 'bonuses' => 0, 'deductions' => 0,
        ];

        foreach ($rules as $rule) {
            $policy = $rule->policy;
            if (!$policy || !$policy->active) {
                continue;
            }
            $amount = $rule->effectiveAmount();
            $when = $policy->applies_when ?? [];
            $value = $this->policyValue($policy->policy_type, $amount, $when, [
                'sessions' => $sessions,
                'work_days' => $workDays,
                'total_minutes' => $totalMinutes,
                'overtime_minutes' => $overtimeMinutes,
                'late_days' => $lateDays,
                'period' => $period,
            ]);

            $snapshot[] = [
                'policy_id' => $policy->id,
                'name' => $policy->name,
                'type' => $policy->policy_type,
                'amount' => $amount,
                'computed' => $value,
            ];

            match ($policy->policy_type) {
                'monthly_base' => $totals['base_salary'] += $value,
                'per_attendance', 'hourly', 'daily' => $totals['attendance_comp'] += $value,
                'overtime' => $totals['overtime'] += $value,
                'allowance' => $totals['allowances'] += $value,
                'bonus' => $totals['bonuses'] += $value,
                'deduction' => $totals['deductions'] += $value,
                default => null,
            };
        }

        // Approved/paid reimbursements inside the period are paid out with payroll.
        $reimbursements = (int) Reimbursement::where('employee_id', $employee->id)
            ->whereIn('status', [Reimbursement::STATUS_DISETUJUI, Reimbursement::STATUS_DIBAYAR])
            ->whereBetween('expense_date', [$period->starts_on->format('Y-m-d'), $period->ends_on->format('Y-m-d')])
            ->sum('amount');

        $gross = $totals['base_salary'] + $totals['attendance_comp'] + $totals['overtime']
            + $totals['allowances'] + $totals['bonuses'] + $reimbursements;
        $net = $gross - $totals['deductions'];

        return [
            'base_salary' => $totals['base_salary'],
            'attendance_comp' => $totals['attendance_comp'],
            'overtime' => $totals['overtime'],
            'allowances' => $totals['allowances'],
            'bonuses' => $totals['bonuses'],
            'reimbursements' => $reimbursements,
            'deductions' => $totals['deductions'],
            'gross' => $gross,
            'net' => $net,
            'work_days' => $workDays,
            'total_minutes' => $totalMinutes,
            'rate_snapshot' => $snapshot,
        ];
    }

    /** @param array<string,mixed> $ctx */
    protected function policyValue(string $type, int $amount, array $when, array $ctx): int
    {
        $eligibleSessions = array_filter($ctx['sessions'], function ($s) use ($when) {
            if (!empty($when['requires_clock_out']) && empty($s['has_clock_out'])) {
                return false;
            }
            if (isset($when['min_minutes']) && $s['total_minutes'] < (int) $when['min_minutes']) {
                return false;
            }
            return true;
        });
        $eligibleDays = count($eligibleSessions);
        $eligibleMinutes = array_sum(array_column($eligibleSessions, 'total_minutes'));

        return match ($type) {
            'per_attendance' => $amount * $eligibleDays,
            'daily' => $amount * $eligibleDays,
            'hourly' => intdiv($amount * $eligibleMinutes, 60),
            'monthly_base' => $ctx['period']->period_type === 'monthly'
                ? ($ctx['work_days'] > 0 ? $amount : 0)
                : intdiv($amount * $this->periodDays($ctx['period']), 30),
            'overtime' => intdiv($amount * $ctx['overtime_minutes'], 60),
            'allowance' => $ctx['work_days'] > 0 ? $amount : 0,
            'bonus' => $amount,
            'deduction' => !empty($when['per_late_day']) ? $amount * $ctx['late_days'] : $amount,
            default => 0,
        };
    }

    protected function periodDays(PayrollPeriod $period): int
    {
        return $period->starts_on->diffInDays($period->ends_on) + 1;
    }

    // ------------------------------------------------------------------
    // Run lifecycle
    // ------------------------------------------------------------------

    /**
     * Build/rebuild draft items for every active employee.
     */
    public function generateDraft(PayrollRun $run): void
    {
        if (!$run->isEditable()) {
            throw new \LogicException('Payroll run is not editable.');
        }
        $period = $run->period;

        $employees = Employee::where('employment_status', 'aktif')->get();
        foreach ($employees as $employee) {
            $sessions = app(AttendanceService::class)->sessionSummaries($employee, $period);
            $calc = $this->calculateItem($employee, $period, $sessions);
            PayrollItem::updateOrCreate(
                ['payroll_run_id' => $run->id, 'employee_id' => $employee->id],
                $calc + ['status' => 'draft']
            );
        }
    }

    /**
     * Finalize: freeze rate snapshots, create payslips. Idempotent-ish (throws if already finalized).
     */
    public function finalize(PayrollRun $run, User $by): void
    {
        if (!in_array($run->status, [PayrollRun::STATUS_DRAFT, PayrollRun::STATUS_REVIEW], true)) {
            throw new \LogicException('Only draft/review runs can be finalized.');
        }

        DB::transaction(function () use ($run, $by) {
            $run->refresh();
            $run->items()->update(['status' => 'finalized']);

            $counter = 0;
            foreach ($run->items as $item) {
                $counter++;
                Payslip::firstOrCreate(
                    ['payroll_item_id' => $item->id],
                    [
                        'reference_code' => sprintf(
                            'SLIP-%s-%d-%04d',
                            $run->period->starts_on->format('Ym'),
                            $run->id,
                            $counter
                        ),
                        'published' => false,
                    ]
                );
            }

            $run->update([
                'status' => PayrollRun::STATUS_FINALIZED,
                'finalized_at' => now(),
                'finalized_by' => $by->id,
                'rate_snapshot' => $run->items()->pluck('rate_snapshot', 'employee_id'),
            ]);
        });

        AuditService::log('payroll.finalized', $by, 'payroll_runs', (string) $run->id);
    }

    public function publish(PayrollRun $run, User $by): void
    {
        if ($run->status !== PayrollRun::STATUS_FINALIZED) {
            throw new \LogicException('Only finalized runs can be published.');
        }
        DB::transaction(function () use ($run, $by) {
            foreach ($run->items as $item) {
                $item->payslip?->update(['published' => true, 'published_at' => now()]);
            }
        });
        AuditService::log('payroll.published', $by, 'payroll_runs', (string) $run->id);
    }

    public function lock(PayrollRun $run, User $by): void
    {
        if ($run->status !== PayrollRun::STATUS_FINALIZED) {
            throw new \LogicException('Only finalized runs can be locked.');
        }
        $run->update(['status' => PayrollRun::STATUS_LOCKED]);
        AuditService::log('payroll.locked', $by, 'payroll_runs', (string) $run->id);
    }
}
