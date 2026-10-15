<?php

namespace Tests\Unit;

use App\Services\WorkingHoursService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class WorkingHoursServiceTest extends TestCase
{
    public function test_normal_day_with_break(): void
    {
        $r = WorkingHoursService::compute(
            CarbonImmutable::parse('2026-10-14 08:00:00', 'UTC'),
            CarbonImmutable::parse('2026-10-14 17:00:00', 'UTC'),
            60
        );

        $this->assertSame(540, $r['gross_minutes']);
        $this->assertSame(60, $r['break_minutes']);
        $this->assertSame(480, $r['total_minutes']);
        $this->assertFalse($r['is_overnight']);
        $this->assertFalse($r['missing_clock_out']);
        $this->assertIsInt($r['total_minutes']);
    }

    public function test_overnight_shift(): void
    {
        $r = WorkingHoursService::compute(
            CarbonImmutable::parse('2026-10-14 22:00:00', 'UTC'),
            CarbonImmutable::parse('2026-10-15 06:00:00', 'UTC'),
            60
        );

        $this->assertSame(480, $r['gross_minutes']);
        $this->assertSame(420, $r['total_minutes']);
        $this->assertTrue($r['is_overnight']);
    }

    public function test_configurable_break_deduction(): void
    {
        $r = WorkingHoursService::compute(
            CarbonImmutable::parse('2026-10-14 08:00:00', 'UTC'),
            CarbonImmutable::parse('2026-10-14 17:00:00', 'UTC'),
            0
        );
        $this->assertSame(540, $r['total_minutes']);
        $this->assertSame(0, $r['break_minutes']);
    }

    public function test_missing_clock_out_is_exception(): void
    {
        $r = WorkingHoursService::compute(
            CarbonImmutable::parse('2026-10-14 08:00:00', 'UTC'),
            null,
            60
        );

        $this->assertTrue($r['missing_clock_out']);
        $this->assertSame(0, $r['break_minutes']); // no break deducted without clock-out
        $this->assertSame(0, $r['overtime_minutes']);
    }

    public function test_late_and_early_flags(): void
    {
        $schedule = ['start' => '08:00', 'end' => '17:00', 'late_tolerance_min' => 15];

        $late = WorkingHoursService::compute(
            CarbonImmutable::parse('2026-10-14 08:20:00', 'UTC'),
            CarbonImmutable::parse('2026-10-14 17:00:00', 'UTC'),
            60, $schedule
        );
        $this->assertTrue($late['is_late']);

        $onTime = WorkingHoursService::compute(
            CarbonImmutable::parse('2026-10-14 08:10:00', 'UTC'),
            CarbonImmutable::parse('2026-10-14 17:00:00', 'UTC'),
            60, $schedule
        );
        $this->assertFalse($onTime['is_late']);

        $early = WorkingHoursService::compute(
            CarbonImmutable::parse('2026-10-14 08:00:00', 'UTC'),
            CarbonImmutable::parse('2026-10-14 16:30:00', 'UTC'),
            60, $schedule
        );
        $this->assertTrue($early['is_early_departure']);
    }

    public function test_overtime_minutes(): void
    {
        $r = WorkingHoursService::compute(
            CarbonImmutable::parse('2026-10-14 08:00:00', 'UTC'),
            CarbonImmutable::parse('2026-10-14 19:00:00', 'UTC'),
            60, null, 480
        );
        // 11h - 1h break = 600 min; overtime = 600 - 480 = 120
        $this->assertSame(600, $r['total_minutes']);
        $this->assertSame(120, $r['overtime_minutes']);
    }

    public function test_format_minutes_indonesian(): void
    {
        $this->assertSame('8 jam', WorkingHoursService::formatMinutes(480));
        $this->assertSame('8 jam 30 menit', WorkingHoursService::formatMinutes(510));
        $this->assertSame('45 menit', WorkingHoursService::formatMinutes(45));
    }
}
