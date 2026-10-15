@extends('layouts.admin')
@section('title','Dashboard')

@section('content')
<h1 class="text-2xl font-extrabold mb-1">Dashboard</h1>
<p class="text-sm text-gray-500 mb-6">{{ $stats['today_label'] }}</p>

<div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
  <div class="card p-5"><div class="text-xs text-gray-500 font-semibold">Karyawan Aktif</div><div class="text-3xl font-extrabold mt-1">{{ $stats['active_employees'] }}</div></div>
  <div class="card p-5"><div class="text-xs text-gray-500 font-semibold">Sedang Bekerja</div><div class="text-3xl font-extrabold mt-1 text-emerald-600">{{ $stats['currently_working'] }}</div></div>
  <div class="card p-5"><div class="text-xs text-gray-500 font-semibold">Shift Selesai Hari Ini</div><div class="text-3xl font-extrabold mt-1">{{ $stats['completed_shifts'] }}</div></div>
  <div class="card p-5"><div class="text-xs text-gray-500 font-semibold">Perlu Review</div><div class="text-3xl font-extrabold mt-1 text-amber-600">{{ $stats['needs_review'] }}</div>
    <a href="{{ route('admin.attendance.corrections') }}" class="text-xs text-emerald-700 font-semibold hover:underline">Lihat koreksi →</a></div>
  <div class="card p-5"><div class="text-xs text-gray-500 font-semibold">Reimburse Menunggu</div><div class="text-3xl font-extrabold mt-1 text-sky-600">{{ $stats['pending_reimbursements'] }}</div>
    <a href="{{ route('admin.reimbursements.index') }}" class="text-xs text-emerald-700 font-semibold hover:underline">Lihat pengajuan →</a></div>
</div>

<div class="card p-5">
  <h2 class="font-bold mb-4">Absensi Hari Ini</h2>
  <div class="overflow-x-auto">
  <table class="data">
    <thead><tr><th>Karyawan</th><th>Masuk</th><th>Keluar</th><th>Total</th><th>Status</th></tr></thead>
    <tbody>
    @forelse($recentSessions as $s)
      @php $in=$s->events->firstWhere('event_type','MASUK'); $out=$s->events->firstWhere('event_type','KELUAR'); @endphp
      <tr>
        <td class="font-semibold"><a href="{{ route('admin.attendance.show',$s) }}" class="text-emerald-700 hover:underline">{{ $s->employee->name }}</a><div class="text-xs text-gray-400">{{ $s->employee->employee_code }}</div></td>
        <td>{{ $in?->server_at?->setTimezone($tz)->format('H:i') ?? '—' }}</td>
        <td>{{ $out?->server_at?->setTimezone($tz)->format('H:i') ?? '—' }}</td>
        <td>{{ $s->total_minutes!==null ? \App\Services\WorkingHoursService::formatMinutes($s->total_minutes) : '—' }}</td>
        <td><span class="badge {{ $s->status==='closed'?'bg-emerald-100 text-emerald-800':($s->status==='exception'?'bg-red-100 text-red-800':'bg-sky-100 text-sky-800') }}">{{ $s->status }}</span></td>
      </tr>
    @empty
      <tr><td colspan="5" class="text-center text-gray-400 py-6">Belum ada absensi hari ini.</td></tr>
    @endforelse
    </tbody>
  </table>
  </div>
</div>
@endsection
