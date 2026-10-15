@extends('layouts.admin')
@section('title','Kelola Penggajian')
@php $rp = fn($n) => 'Rp ' . number_format((int)$n,0,',','.'); @endphp

@section('content')
<a href="{{ route('admin.payroll.index') }}" class="text-sm text-emerald-700 font-semibold hover:underline">← Penggajian</a>
<div class="flex items-center justify-between mt-2 mb-6 flex-wrap gap-3">
  <div>
    <h1 class="text-2xl font-extrabold">{{ $run->period->name }}</h1>
    <p class="text-sm text-gray-500">{{ $run->period->starts_on->format('d M Y') }} – {{ $run->period->ends_on->format('d M Y') }} · <span class="badge bg-gray-100">{{ $run->status }}</span></p>
  </div>
  <div class="flex gap-2 flex-wrap">
    @if($run->status==='draft')
      <form method="POST" action="{{ route('admin.payroll.runs.generate',$run) }}">@csrf<button class="btn btn-primary">⚙️ Generate Draf</button></form>
      <form method="POST" action="{{ route('admin.payroll.runs.review',$run) }}">@csrf<button class="btn btn-ghost">Kirim ke Review</button></form>
    @endif
    @if(in_array($run->status,['draft','review']))
      <form method="POST" action="{{ route('admin.payroll.runs.finalize',$run) }}">@csrf<button class="btn btn-primary" onclick="return confirm('Finalisasi? Rate akan dikunci (snapshot) dan tidak bisa diubah.')">🔒 Finalisasi</button></form>
    @endif
    @if($run->status==='finalized')
      <form method="POST" action="{{ route('admin.payroll.runs.publish',$run) }}">@csrf<button class="btn btn-primary">📢 Publikasikan Slip</button></form>
      <form method="POST" action="{{ route('admin.payroll.runs.lock',$run) }}">@csrf<button class="btn btn-danger" onclick="return confirm('Kunci periode ini secara permanen?')">🔒 Kunci</button></form>
    @endif
  </div>
</div>

<div class="card p-2 overflow-x-auto">
<table class="data">
  <thead><tr><th>Karyawan</th><th>Hari</th><th>Gaji Pokok</th><th>Insentif</th><th>Lembur</th><th>Tunjangan</th><th>Bonus</th><th>Reimburse</th><th>Potongan</th><th>Total</th></tr></thead>
  <tbody>
  @forelse($run->items as $it)
    <tr>
      <td class="font-semibold whitespace-nowrap">{{ $it->employee->name }}<div class="text-xs text-gray-400 font-normal">{{ $it->employee->employee_code }}</div></td>
      <td>{{ $it->work_days }}</td>
      <td class="whitespace-nowrap">{{ $rp($it->base_salary) }}</td>
      <td class="whitespace-nowrap">{{ $rp($it->attendance_comp) }}</td>
      <td class="whitespace-nowrap">{{ $rp($it->overtime) }}</td>
      <td class="whitespace-nowrap">{{ $rp($it->allowances) }}</td>
      <td class="whitespace-nowrap">{{ $rp($it->bonuses) }}</td>
      <td class="whitespace-nowrap">{{ $rp($it->reimbursements) }}</td>
      <td class="whitespace-nowrap text-red-600">({{ $rp($it->deductions) }})</td>
      <td class="whitespace-nowrap font-extrabold text-emerald-700">{{ $rp($it->net) }}</td>
    </tr>
  @empty
    <tr><td colspan="10" class="text-center text-gray-400 py-8">Belum ada draf. Klik "Generate Draf".</td></tr>
  @endforelse
  </tbody>
  @if($run->items->count())
  <tfoot><tr class="font-extrabold bg-gray-50">
    <td colspan="9" class="text-right">Total</td>
    <td class="whitespace-nowrap text-emerald-700">{{ $rp($run->items->sum('net')) }}</td>
  </tr></tfoot>
  @endif
</table>
</div>
@if($run->finalized_at)<p class="text-xs text-gray-500 mt-3">Difinalisasi {{ $run->finalized_at->format('d M Y H:i') }} oleh {{ $run->finalizer?->name }}. Rate dikunci pada snapshot.</p>@endif
@endsection
