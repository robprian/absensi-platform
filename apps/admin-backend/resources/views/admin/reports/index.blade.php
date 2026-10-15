@extends('layouts.admin')
@section('title','Laporan')

@section('content')
<h1 class="text-2xl font-extrabold mb-6">Laporan Kehadiran</h1>

<div class="card p-4 mb-4">
  <form method="GET" class="flex gap-3 items-end flex-wrap">
    <div><label class="label">Dari</label><input type="date" name="date_from" value="{{ $dateFrom }}" class="input"></div>
    <div><label class="label">Sampai</label><input type="date" name="date_to" value="{{ $dateTo }}" class="input"></div>
    <button class="btn btn-ghost">Tampilkan</button>
    <a href="{{ route('admin.attendance.export', ['date_from'=>$dateFrom,'date_to'=>$dateTo]) }}" class="btn btn-primary">Ekspor XLSX</a>
  </form>
</div>

<div class="card p-2 overflow-x-auto">
<table class="data">
  <thead><tr><th>Karyawan</th><th>Hari Tercatat</th><th>Selesai</th><th>Pengecualian</th><th>Total Jam Kerja</th></tr></thead>
  <tbody>
  @foreach($rows as $r)
    <tr>
      <td class="font-semibold">{{ $r['employee']->name }}<div class="text-xs text-gray-400 font-normal">{{ $r['employee']->employee_code }}</div></td>
      <td>{{ $r['days'] }}</td>
      <td>{{ $r['closed_days'] }}</td>
      <td>{{ $r['exceptions'] ? '⚠️ '.$r['exceptions'] : '—' }}</td>
      <td class="font-semibold">{{ $fmt($r['total_minutes']) }}</td>
    </tr>
  @endforeach
  </tbody>
</table>
</div>
@endsection
