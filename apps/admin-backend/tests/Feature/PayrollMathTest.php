<?php

namespace Tests\Feature;

use App\Models\CompensationPolicy;
use App\Models\EmployeeCompensationRule;
use App\Models\PayrollPeriod;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\MakesTestUsers;

class PayrollMathTest extends TestCase
{
    use RefreshDatabase, MakesTestUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBase();
    }

    protected function makePeriod(string $type = 'monthly'): PayrollPeriod
    {
        return PayrollPeriod::create([
            'name' => 'Oktober 2026',
            'period_type' => $type,
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-10-31',
            'status' => 'open',
        ]);
    }

    protected function assignPolicy($employee, string $type, int $amount, array $when = []): void
    {
        $policy = CompensationPolicy::create([
            'name' => "Test $type",
            'policy_type' => $type,
            'amount' => $amount,
            'applies_when' => $when ?: null,
        ]);
        EmployeeCompensationRule::create([
            'employee_id' => $employee->id,
            'compensation_policy_id' => $policy->id,
            'effective_from' => '2026-01-01',
            'active' => true,
        ]);
    }

    protected function sessions(int $days, int $minutesPerDay = 480, int $overtime = 0): array
    {
        $out = [];
        for ($i = 1; $i <= $days; $i++) {
            $out[] = [
                'work_date' => "2026-10-" . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'total_minutes' => $minutesPerDay,
                'overtime_minutes' => $overtime,
                'is_late' => false,
                'has_clock_out' => true,
            ];
        }
        return $out;
    }

    public function test_per_attendance_and_monthly_base_integer_math(): void
    {
        $employee = $this->makeEmployee();
        $period = $this->makePeriod('monthly');

        $this->assignPolicy($employee, 'monthly_base', 3000000);
        $this->assignPolicy($employee, 'per_attendance', 80000, ['requires_clock_out' => true]);

        $calc = app(PayrollService::class)->calculateItem($employee, $period, $this->sessions(20));

        $this->assertSame(3000000, $calc['base_salary']);
        $this->assertSame(1600000, $calc['attendance_comp']); // 80.000 x 20
        $this->assertSame(4600000, $calc['gross']);
        $this->assertSame(4600000, $calc['net']);
        $this->assertSame(20, $calc['work_days']);
        $this->assertSame(9600, $calc['total_minutes']);

        // Every money field must be a true integer — never float.
        foreach (['base_salary', 'attendance_comp', 'overtime', 'allowances', 'bonuses', 'reimbursements', 'deductions', 'gross', 'net'] as $f) {
            $this->assertIsInt($calc[$f], "Field $f is not int");
        }
    }

    public function test_hourly_prorated_per_minute(): void
    {
        $employee = $this->makeEmployee();
        $period = $this->makePeriod('weekly');

        $this->assignPolicy($employee, 'hourly', 20000);

        // 7.5 hours = 450 minutes -> intdiv(20000*450, 60) = 150000
        $calc = app(PayrollService::class)->calculateItem($employee, $period, $this->sessions(1, 450));

        $this->assertSame(150000, $calc['attendance_comp']);
        $this->assertIsInt($calc['attendance_comp']);
    }

    public function test_overtime_prorated(): void
    {
        $employee = $this->makeEmployee();
        $period = $this->makePeriod('monthly');

        $this->assignPolicy($employee, 'overtime', 30000);

        // 90 overtime minutes -> intdiv(30000*90, 60) = 45000
        $calc = app(PayrollService::class)->calculateItem($employee, $period, $this->sessions(1, 570, 90));

        $this->assertSame(45000, $calc['overtime']);
    }

    public function test_min_minutes_eligibility(): void
    {
        $employee = $this->makeEmployee();
        $period = $this->makePeriod('monthly');

        // Requires at least 240 minutes to earn the daily incentive.
        $this->assignPolicy($employee, 'per_attendance', 80000, ['min_minutes' => 240, 'requires_clock_out' => true]);

        $sessions = array_merge($this->sessions(3, 480), $this->sessions(1, 120));
        $calc = app(PayrollService::class)->calculateItem($employee, $period, $sessions);

        $this->assertSame(240000, $calc['attendance_comp']); // only 3 eligible days
    }

    public function test_no_float_arithmetic_anywhere(): void
    {
        $employee = $this->makeEmployee();
        $period = $this->makePeriod('monthly');

        $this->assignPolicy($employee, 'monthly_base', 3333333);
        $this->assignPolicy($employee, 'hourly', 19999);
        $this->assignPolicy($employee, 'deduction', 50000);

        $calc = app(PayrollService::class)->calculateItem($employee, $period, $this->sessions(7, 481));

        // gross = 3333333 + intdiv(19999*3367,60); net = gross - 50000
        $expectedHourly = intdiv(19999 * 3367, 60);
        $this->assertSame($expectedHourly, $calc['attendance_comp']);
        $this->assertSame(3333333 + $expectedHourly, $calc['gross']);
        $this->assertSame(3333333 + $expectedHourly - 50000, $calc['net']);
        $this->assertSame($calc['gross'] - $calc['deductions'], $calc['net']);
    }
}
