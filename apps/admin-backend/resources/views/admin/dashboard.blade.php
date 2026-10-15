@extends('layouts.admin')
@section('title','Dashboard')

@section('content')
<div class="mb-6">
  <h1 class="text-2xl font-extrabold tracking-tight">Dashboard</h1>
  <p class="text-sm text-gray-500 mt-1">{{ $stats['today_label'] }}</p>
</div>

<div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
  <div class="card p-5 relative overflow-hidden">
    <div class="absolute top-0 right-0 w-24 h-24 rounded-full bg-emerald-50 -mr-8 -mt-8"></div>
    <div class="relative">
      <div class="flex items-center gap-2 text-xs text-gray-500 font-semibold"><span class="text-lg">👥</span> Karyawan Aktif</div>
      <div class="text-3xl font-extrabold mt-2 tabular-nums">{{ $stats['active_employees'] }}</div>
    </div>
  </div>
  <div class="card p-5 relative overflow-hidden">
    <div class="absolute top-0 right-0 w-24 h-24 rounded-full bg-emerald-100 -mr-8 -mt-8"></div>
    <div class="relative">
      <div class="flex items-center gap-2 text-xs text-gray-500 font-semibold"><span class="text-lg">🟢</span> Sedang Bekerja</div>
      <div class="text-3xl font-extrabold mt-2 text-emerald-600 tabular-nums">{{ $stats['currently_working'] }}</div>
    </div>
  </div>
  <div class="card p-5 relative overflow-hidden">
    <div class="absolute top-0 right-0 w-24 h-24 rounded-full bg-sky-50 -mr-8 -mt-8"></div>
    <div class="relative">
      <div class="flex items-center gap-2 text-xs text-gray-500 font-semibold"><span class="text-lg">✅</span> Shift Selesai</div>
      <div class="text-3xl font-extrabold mt-2 tabular-nums">{{ $stats['completed_shifts'] }}</div>
      <div class="text-xs text-gray-400 mt-1">hari ini</div>
    </div>
  </div>
  <div class="card p-5 relative overflow-hidden border-l-4 border-l-amber-400">
    <div class="relative">
      <div class="flex items-center gap-2 text-xs text-gray-500 font-semibold"><span class="text-lg">⚠️</span> Perlu Review</div>
      <div class="text-3xl font-extrabold mt-2 text-amber-600 tabular-nums">{{ $stats['needs_review'] }}</div>
      <a href="{{ route('admin.attendance.corrections') }}" class="inline-flex items-center gap-1 text-xs font-semibold mt-2 hover:gap-2 transition-all" style="color: var(--brand-primary)">Lihat koreksi →</a>
    </div>
  </div>
  <div class="card p-5 relative overflow-hidden border-l-4 border-l-sky-400">
    <div class="relative">
      <div class="flex items-center gap-2 text-xs text-gray-500 font-semibold"><span class="text-lg">🧾</span> Reimburse Menunggu</div>
      <div class="text-3xl font-extrabold mt-2 text-sky-600 tabular-nums">{{ $stats['pending_reimbursements'] }}</div>
      <a href="{{ route('admin.reimbursements.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold mt-2 hover:gap-2 transition-all" style="color: var(--brand-primary)">Lihat pengajuan →</a>
    </div>
  </div>
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
