@extends('layouts.admin')
@section('title','Detail Absensi')

@section('content')
<a href="{{ route('admin.attendance.index') }}" class="text-sm text-emerald-700 font-semibold hover:underline">← Kembali</a>
<h1 class="text-2xl font-extrabold mt-2 mb-1">{{ $session->employee->name }}</h1>
<p class="text-sm text-gray-500 mb-6">{{ $session->work_date->locale('id')->isoFormat('dddd, D MMMM Y') }} · <span class="badge bg-gray-100">{{ $session->status }}</span></p>

<div class="grid md:grid-cols-2 gap-4 mb-6">
@foreach($session->events as $e)
  <div class="card p-5">
    <div class="flex items-center justify-between mb-3">
      <span class="font-extrabold {{ $e->event_type==='MASUK'?'text-emerald-700':'text-red-600' }}">{{ $e->event_type==='MASUK'?'🟢 ABSEN MASUK':'🔴 ABSEN KELUAR' }}</span>
      <span class="badge {{ $e->verification_status==='verified'?'bg-emerald-100 text-emerald-800':($e->verification_status==='flagged'?'bg-amber-100 text-amber-800':'bg-red-100 text-red-800') }}">{{ $e->verification_status }}</span>
    </div>
    <dl class="text-sm space-y-1.5">
      <div class="flex justify-between"><dt class="text-gray-500">Waktu server (WIB)</dt><dd class="font-semibold">{{ $e->server_at->setTimezone($tz)->format('H:i:s') }}</dd></div>
      <div class="flex justify-between"><dt class="text-gray-500">Koordinat</dt><dd class="font-mono text-xs">{{ $e->latitude }}, {{ $e->longitude }}</dd></div>
      <div class="flex justify-between"><dt class="text-gray-500">Akurasi</dt><dd>±{{ $e->accuracy_meters }} m</dd></div>
      @if($e->address)<div><dt class="text-gray-500">Alamat</dt><dd>{{ $e->address }}</dd></div>@endif
      @if($e->rejection_reason)<div><dt class="text-gray-500">Catatan</dt><dd class="text-amber-700">{{ $e->rejection_reason }}</dd></div>@endif
      <div><a href="https://www.google.com/maps?q={{ $e->latitude }},{{ $e->longitude }}" target="_blank" rel="noopener" class="text-emerald-700 font-semibold hover:underline">📍 Lihat di peta</a></div>
    </dl>
  </div>
@endforeach
</div>

<h2 class="font-bold mb-3">Foto Bukti</h2>
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
@forelse($photos as $p)
  <div class="card p-2">
    <a href="{{ $p['url'] }}" target="_blank"><img src="{{ $p['url'] }}" class="rounded-xl w-full aspect-[3/4] object-cover" loading="lazy" alt="foto absensi"></a>
    <div class="p-2 text-xs"><span class="badge {{ $p['event_type']==='MASUK'?'bg-emerald-100 text-emerald-800':'bg-red-100 text-red-800' }}">{{ $p['event_type'] }}</span>
    <div class="text-gray-400 mt-1">{{ $p['status'] }}</div></div>
  </div>
@empty
  <p class="text-sm text-gray-400">Belum ada foto.</p>
@endforelse
</div>

@if($session->total_minutes!==null)
<div class="card p-5">
  <h2 class="font-bold mb-2">Ringkasan Jam Kerja</h2>
  <p class="text-sm">Total: <strong>{{ \App\Services\WorkingHoursService::formatMinutes($session->total_minutes) }}</strong></p>
</div>
@endif
@endsection
