@extends('layouts.admin')
@section('title','Absensi')

@section('content')
<div class="flex items-center justify-between mb-6 flex-wrap gap-3">
  <h1 class="text-2xl font-extrabold">Absensi</h1>
  <div class="flex gap-2">
    <a href="{{ route('admin.attendance.corrections') }}" class="btn btn-ghost">Koreksi & Pengecualian</a>
    <a href="{{ route('admin.attendance.export', request()->only(['employee_id','date_from','date_to','status'])) }}" class="btn btn-primary">Ekspor XLSX</a>
  </div>
</div>

<div class="card p-4 mb-4">
  <form method="GET" class="flex flex-wrap gap-3 items-end">
    <div><label class="label">Karyawan</label>
      <select name="employee_id" class="input min-w-[200px]"><option value="">Semua</option>
      @foreach($employees as $e)<option value="{{ $e->id }}" @selected(request('employee_id')==$e->id)>{{ $e->name }}</option>@endforeach</select></div>
    <div><label class="label">Dari</label><input type="date" name="date_from" value="{{ request('date_from') }}" class="input"></div>
    <div><label class="label">Sampai</label><input type="date" name="date_to" value="{{ request('date_to') }}" class="input"></div>
    <div><label class="label">Status</label>
      <select name="status" class="input"><option value="">Semua</option>
      @foreach(['open'=>'Berjalan','closed'=>'Selesai','exception'=>'Pengecualian'] as $k=>$v)<option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>@endforeach</select></div>
    <button class="btn btn-ghost">Filter</button>
  </form>
</div>

<div class="card p-2 overflow-x-auto">
<table class="data">
  <thead><tr><th>Tanggal</th><th>Karyawan</th><th>Masuk</th><th>Keluar</th><th>Total</th><th>Verifikasi</th><th>Status</th></tr></thead>
  <tbody>
  @foreach($sessions as $s)
    @php $in=$s->events->firstWhere('event_type','MASUK'); $out=$s->events->firstWhere('event_type','KELUAR'); @endphp
    <tr>
      <td class="whitespace-nowrap"><a href="{{ route('admin.attendance.show',$s) }}" class="text-emerald-700 font-semibold hover:underline">{{ $s->work_date->locale('id')->isoFormat('D MMM Y') }}</a></td>
      <td class="font-semibold">{{ $s->employee->name }}<div class="text-xs text-gray-400 font-normal">{{ $s->employee->employee_code }}</div></td>
      <td>{{ $in?->server_at?->setTimezone($tz)->format('H:i') ?? '—' }}</td>
      <td>{{ $out?->server_at?->setTimezone($tz)->format('H:i') ?? '—' }}</td>
      <td>{{ $s->total_minutes!==null ? \App\Services\WorkingHoursService::formatMinutes($s->total_minutes) : '—' }}</td>
      <td>@if($in)<span class="badge {{ $in->verification_status==='verified'?'bg-emerald-100 text-emerald-800':($in->verification_status==='flagged'?'bg-amber-100 text-amber-800':'bg-red-100 text-red-800') }}">{{ $in->verification_status }}</span>@else — @endif</td>
      <td><span class="badge {{ $s->status==='closed'?'bg-emerald-100 text-emerald-800':($s->status==='exception'?'bg-red-100 text-red-800':'bg-sky-100 text-sky-800') }}">{{ $s->status }}</span></td>
    </tr>
  @endforeach
  </tbody>
</table>
</div>
<div class="mt-4">{{ $sessions->links() }}</div>
@endsection
