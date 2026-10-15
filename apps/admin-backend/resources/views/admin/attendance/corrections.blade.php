@extends('layouts.admin')
@section('title','Koreksi Absensi')

@section('content')
<h1 class="text-2xl font-extrabold mb-6">Koreksi & Pengecualian</h1>

<div class="card p-2 overflow-x-auto">
<table class="data">
  <thead><tr><th>Tanggal</th><th>Karyawan</th><th>Jenis</th><th>Alasan</th><th>Diajukan</th><th>Status</th><th></th></tr></thead>
  <tbody>
  @foreach($corrections as $c)
    <tr>
      <td class="whitespace-nowrap">{{ $c->session->work_date->locale('id')->isoFormat('D MMM Y') }}</td>
      <td class="font-semibold">{{ $c->session->employee->name }}</td>
      <td>{{ $c->type }}</td>
      <td class="max-w-xs">{{ $c->reason }}</td>
      <td class="text-xs text-gray-500">{{ $c->requester?->name ?? 'Sistem' }}<br>{{ $c->created_at->format('d M H:i') }}</td>
      <td><span class="badge {{ $c->status==='pending'?'bg-amber-100 text-amber-800':($c->status==='approved'?'bg-emerald-100 text-emerald-800':'bg-red-100 text-red-800') }}">{{ $c->status }}</span></td>
      <td class="whitespace-nowrap">
        @if($c->status==='pending')
        <form method="POST" action="{{ route('admin.attendance.corrections.approve',$c) }}" class="inline">@csrf<button class="btn btn-primary !py-1 !px-3 text-xs">Setujui</button></form>
        <form method="POST" action="{{ route('admin.attendance.corrections.reject',$c) }}" class="inline">@csrf<button class="btn btn-danger !py-1 !px-3 text-xs" onclick="return confirm('Tolak koreksi ini?')">Tolak</button></form>
        @endif
      </td>
    </tr>
  @endforeach
  </tbody>
</table>
</div>
<div class="mt-4">{{ $corrections->links() }}</div>
@endsection
