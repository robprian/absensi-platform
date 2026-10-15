<?php

namespace App\Exports;

use App\Models\AttendanceEvent;
use App\Models\AttendanceSession;
use App\Services\WorkingHoursService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AttendanceExport implements FromCollection, WithHeadings
{
    public function __construct(protected array $filters = []) {}

    public function headings(): array
    {
        return [
            'Tanggal', 'Kode Karyawan', 'Nama', 'Masuk', 'Keluar',
            'Total Kerja', 'Status', 'Lokasi', 'Verifikasi',
        ];
    }

    public function collection()
    {
        $f = $this->filters;
        return AttendanceSession::with(['employee', 'events', 'workSite'])
            ->when($f['employee_id'] ?? null, fn ($q, $id) => $q->where('employee_id', $id))
            ->when($f['date_from'] ?? null, fn ($q, $d) => $q->where('work_date', '>=', $d))
            ->when($f['date_to'] ?? null, fn ($q, $d) => $q->where('work_date', '<=', $d))
            ->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('work_date')
            ->get()
            ->map(function ($s) {
                $masuk = $s->events->firstWhere('event_type', AttendanceEvent::TYPE_MASUK);
                $keluar = $s->events->firstWhere('event_type', AttendanceEvent::TYPE_KELUAR);
                return [
                    $s->work_date->format('Y-m-d'),
                    $s->employee->employee_code,
                    $s->employee->name,
                    $masuk?->server_at?->format('H:i:s') ?? '-',
                    $keluar?->server_at?->format('H:i:s') ?? '-',
                    $s->total_minutes !== null ? WorkingHoursService::formatMinutes($s->total_minutes) : '-',
                    $s->status,
                    $s->workSite?->name ?? ($masuk?->address ?? '-'),
                    $masuk?->verification_status ?? '-',
                ];
            });
    }
}
