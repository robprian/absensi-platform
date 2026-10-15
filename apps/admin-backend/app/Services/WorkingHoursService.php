<?php

namespace App\Services;

use Carbon\CarbonImmutable;

/**
 * Pure, unit-testable working-hours computation. Server-side source of truth.
 *
 * Input: clock-in / clock-out timestamps (UTC CarbonImmutable), break minutes,
 * optional schedule (start/end + late/early tolerance), overtime threshold.
 *
 * Returns integer minutes and boolean flags. Never floats.
 */
class WorkingHoursService
{
    /**
     * @param CarbonImmutable $clockIn
     * @param CarbonImmutable|null $clockOut  null => missing clock-out (exception)
     * @param int $breakMinutes  configurable break deduction
     * @param array|null $schedule  ['start' => 'HH:MM', 'end' => 'HH:MM', 'late_tolerance_min' => int]
     * @param int $overtimeThresholdMin  minutes after which work counts as overtime
     * @return array{
     *   total_minutes: int, gross_minutes: int, break_minutes: int,
     *   is_overnight: bool, is_late: bool, is_early_departure: bool,
     *   overtime_minutes: int, missing_clock_out: bool
     * }
     */
    public static function compute(
        CarbonImmutable $clockIn,
        ?CarbonImmutable $clockOut,
        int $breakMinutes = 60,
        ?array $schedule = null,
        int $overtimeThresholdMin = 480,
    ): array {
        $missingClockOut = $clockOut === null;
        $end = $clockOut ?? $clockIn->endOfDay();

        $gross = (int) $clockIn->diffInMinutes($end, false);
        $gross = max(0, $gross);

        $break = $missingClockOut ? 0 : min($breakMinutes, $gross);
        $total = $gross - $break;

        $isOvernight = $clockIn->format('Y-m-d') !== $end->format('Y-m-d');

        $isLate = false;
        $isEarly = false;
        if ($schedule && isset($schedule['start'])) {
            $tolerance = (int) ($schedule['late_tolerance_min'] ?? 0);
            $schedStart = $clockIn->setTimeFromTimeString($schedule['start']);
            $isLate = $clockIn->greaterThan($schedStart->addMinutes($tolerance));
            if (!$missingClockOut && isset($schedule['end'])) {
                $schedEnd = $clockIn->setTimeFromTimeString($schedule['end']);
                if (!empty($schedule['overnight'])) {
                    $schedEnd = $schedEnd->addDay();
                }
                $isEarly = $end->lessThan($schedEnd->subMinutes($tolerance));
            }
        }

        $overtime = $missingClockOut ? 0 : max(0, $total - $overtimeThresholdMin);

        return [
            'total_minutes' => (int) $total,
            'gross_minutes' => (int) $gross,
            'break_minutes' => (int) $break,
            'is_overnight' => $isOvernight,
            'is_late' => $isLate,
            'is_early_departure' => $isEarly,
            'overtime_minutes' => (int) $overtime,
            'missing_clock_out' => $missingClockOut,
        ];
    }

    /** Human-readable "X jam Y menit" (Bahasa Indonesia). */
    public static function formatMinutes(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        if ($h > 0 && $m > 0) {
            return "{$h} jam {$m} menit";
        }
        if ($h > 0) {
            return "{$h} jam";
        }
        return "{$m} menit";
    }
}
