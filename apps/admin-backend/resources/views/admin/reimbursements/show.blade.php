@extends('layouts.admin')
@section('title','Detail Reimburse')
@php $rp = fn($n) => 'Rp ' . number_format((int)$n,0,',','.'); @endphp

@section('content')
<a href="{{ route('admin.reimbursements.index') }}" class="text-sm text-emerald-700 font-semibold hover:underline">← Reimburse</a>
<h1 class="text-2xl font-extrabold mt-2 mb-6">Pengajuan Reimburse</h1>

<div class="grid md:grid-cols-2 gap-6">
  <div class="card p-6">
    <dl class="text-sm space-y-2.5">
      <div class="flex justify-between"><dt class="text-gray-500">Karyawan</dt><dd class="font-semibold">{{ $reimbursement->employee->name }}</dd></div>
      <div class="flex justify-between"><dt class="text-gray-500">Kategori</dt><dd>{{ $reimbursement->category }}</dd></div>
      <div class="flex justify-between"><dt class="text-gray-500">Nominal</dt><dd class="font-extrabold text-lg">{{ $rp($reimbursement->amount) }}</dd></div>
      <div class="flex justify-between"><dt class="text-gray-500">Tanggal pengeluaran</dt><dd>{{ $reimbursement->expense_date->locale('id')->isoFormat('D MMMM Y') }}</dd></div>
      <div><dt class="text-gray-500">Keterangan</dt><dd>{{ $reimbursement->description ?? '—' }}</dd></div>
      <div class="flex justify-between"><dt class="text-gray-500">Status</dt><dd><span class="badge bg-gray-100">{{ $reimbursement->status }}</span></dd></div>
      @if($reimbursement->reviewer)<div class="flex justify-between"><dt class="text-gray-500">Direview oleh</dt><dd>{{ $reimbursement->reviewer->name }} · {{ $reimbursement->reviewed_at?->format('d M Y H:i') }}</dd></div>@endif
      @if($reimbursement->review_note)<div><dt class="text-gray-500">Catatan review</dt><dd>{{ $reimbursement->review_note }}</dd></div>@endif
    </dl>

    <div class="flex gap-2 mt-6 flex-wrap">
      @if($reimbursement->status==='menunggu')
        <form method="POST" action="{{ route('admin.reimbursements.transition',$reimbursement) }}">@csrf
          <input type="hidden" name="status" value="disetujui"><button class="btn btn-primary">✅ Setujui</button></form>
        <form method="POST" action="{{ route('admin.reimbursements.transition',$reimbursement) }}">@csrf
          <input type="hidden" name="status" value="ditolak"><button class="btn btn-danger" onclick="return confirm('Tolak pengajuan ini?')">❌ Tolak</button></form>
      @elseif($reimbursement->status==='disetujui')
        <form method="POST" action="{{ route('admin.reimbursements.transition',$reimbursement) }}">@csrf
          <input type="hidden" name="status" value="dibayar"><button class="btn btn-primary" onclick="return confirm('Tandai sudah dibayar?')">💸 Tandai Dibayar</button></form>
      @endif
    </div>
  </div>
  <div class="card p-4">
    <h2 class="font-bold mb-3">Bukti Pengeluaran</h2>
    @if($receiptUrl)
      <a href="{{ $receiptUrl }}" target="_blank"><img src="{{ $receiptUrl }}" class="rounded-xl w-full object-contain max-h-[500px] bg-gray-50" alt="bukti"></a>
    @else
      <p class="text-sm text-gray-400">Bukti belum tersedia (masih menunggu sinkronisasi penyimpanan).</p>
    @endif
  </div>
</div>
@endsection
