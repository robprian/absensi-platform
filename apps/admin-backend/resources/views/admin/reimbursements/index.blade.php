@extends('layouts.admin')
@section('title','Reimburse')
@php $rp = fn($n) => 'Rp ' . number_format((int)$n,0,',','.'); @endphp

@section('content')
<h1 class="text-2xl font-extrabold mb-6">Pengajuan Reimburse</h1>

<div class="card p-4 mb-4">
  <form method="GET" class="flex gap-3 items-end">
    <div><label class="label">Status</label>
      <select name="status" class="input"><option value="">Semua</option>
      @foreach(['menunggu'=>'Menunggu','disetujui'=>'Disetujui','ditolak'=>'Ditolak','dibayar'=>'Dibayar'] as $k=>$v)<option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>@endforeach</select></div>
    <button class="btn btn-ghost">Filter</button>
  </form>
</div>

<div class="card p-2 overflow-x-auto">
<table class="data">
  <thead><tr><th>Tanggal</th><th>Karyawan</th><th>Kategori</th><th>Nominal</th><th>Keterangan</th><th>Bukti</th><th>Status</th><th></th></tr></thead>
  <tbody>
  @foreach($claims as $c)
    <tr>
      <td class="whitespace-nowrap text-xs">{{ $c->expense_date->format('d M Y') }}</td>
      <td class="font-semibold">{{ $c->employee->name }}</td>
      <td>{{ $c->category }}</td>
      <td class="whitespace-nowrap font-semibold">{{ $rp($c->amount) }}</td>
      <td class="max-w-[200px] text-xs">{{ $c->description ?? '—' }}</td>
      <td>@if($c->receipt_url)<a href="{{ $c->receipt_url }}" target="_blank" class="text-emerald-700 font-semibold hover:underline text-sm">Lihat</a>@else<span class="text-xs text-gray-400">—</span>@endif</td>
      <td><span class="badge {{ $c->status==='menunggu'?'bg-amber-100 text-amber-800':($c->status==='disetujui'?'bg-sky-100 text-sky-800':($c->status==='dibayar'?'bg-emerald-100 text-emerald-800':'bg-red-100 text-red-800')) }}">{{ $c->status }}</span></td>
      <td class="whitespace-nowrap"><a href="{{ route('admin.reimbursements.show',$c) }}" class="text-emerald-700 font-semibold hover:underline text-sm">Kelola →</a></td>
    </tr>
  @endforeach
  </tbody>
</table>
</div>
<div class="mt-4">{{ $claims->links() }}</div>
@endsection
