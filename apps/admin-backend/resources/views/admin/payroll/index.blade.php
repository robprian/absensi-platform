@extends('layouts.admin')
@section('title','Penggajian')
@php $rp = fn($n) => 'Rp ' . number_format($n,0,',','.'); @endphp

@section('content')
<h1 class="text-2xl font-extrabold mb-6">Penggajian</h1>

<div class="grid lg:grid-cols-2 gap-6 mb-8">
  <div class="card p-5">
    <h2 class="font-bold mb-3">Kebijakan Kompensasi</h2>
    <table class="data mb-4">
      <thead><tr><th>Nama</th><th>Tipe</th><th>Nominal</th><th>Aktif</th></tr></thead>
      <tbody>
      @foreach($policies as $p)
        <tr><td class="font-semibold">{{ $p->name }}</td><td class="text-xs">{{ $p->policy_type }}</td>
        <td class="whitespace-nowrap">{{ $rp($p->amount) }}</td>
        <td>{{ $p->active ? '✅' : '❌' }}</td></tr>
      @endforeach
      </tbody>
    </table>
    <details class="text-sm"><summary class="cursor-pointer font-semibold text-emerald-700">+ Tambah kebijakan</summary>
      <form method="POST" action="{{ route('admin.payroll.policies.store') }}" class="mt-3 space-y-3">@csrf
        <div><label class="label">Nama</label><input name="name" required class="input" placeholder="cth: Insentif Kehadiran"></div>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="label">Tipe</label><select name="policy_type" class="input">
            @foreach(['per_attendance'=>'Per kehadiran','hourly'=>'Per jam','daily'=>'Harian','monthly_base'=>'Gaji pokok bulanan','overtime'=>'Lembur','allowance'=>'Tunjangan','bonus'=>'Bonus','deduction'=>'Potongan'] as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
          <div><label class="label">Nominal (Rp)</label><input name="amount" type="number" min="0" required class="input" placeholder="80000"></div>
        </div>
        <button class="btn btn-primary">Simpan</button>
      </form>
    </details>
    <details class="text-sm mt-4"><summary class="cursor-pointer font-semibold text-emerald-700">Tetapkan ke karyawan</summary>
      <form method="POST" action="{{ route('admin.payroll.rules.assign') }}" class="mt-3 space-y-3">@csrf
        <div class="grid grid-cols-2 gap-3">
          <div><label class="label">Karyawan</label><select name="employee_id" class="input">@foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>@endforeach</select></div>
          <div><label class="label">Kebijakan</label><select name="compensation_policy_id" class="input">@foreach($policies as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div>
          <div><label class="label">Nominal khusus (opsional)</label><input name="custom_amount" type="number" min="0" class="input" placeholder="= kebijakan"></div>
          <div><label class="label">Berlaku sejak</label><input name="effective_from" type="date" required class="input" value="{{ now()->format('Y-m-d') }}"></div>
        </div>
        <button class="btn btn-primary">Tetapkan</button>
      </form>
    </details>
  </div>

  <div class="card p-5">
    <h2 class="font-bold mb-3">Periode Penggajian</h2>
    <table class="data mb-4">
      <thead><tr><th>Nama</th><th>Tipe</th><th>Rentang</th></tr></thead>
      <tbody>
      @foreach($periods as $per)
        <tr><td class="font-semibold">{{ $per->name }}</td><td class="text-xs">{{ $per->period_type }}</td>
        <td class="text-xs whitespace-nowrap">{{ $per->starts_on->format('d M') }} – {{ $per->ends_on->format('d M Y') }}</td></tr>
      @endforeach
      </tbody>
    </table>
    {{ $periods->links() }}
    <details class="text-sm mt-3"><summary class="cursor-pointer font-semibold text-emerald-700">+ Buat periode</summary>
      <form method="POST" action="{{ route('admin.payroll.periods.store') }}" class="mt-3 space-y-3">@csrf
        <div><label class="label">Nama</label><input name="name" required class="input" placeholder="Oktober 2026"></div>
        <div class="grid grid-cols-3 gap-3">
          <div><label class="label">Tipe</label><select name="period_type" class="input"><option value="monthly">Bulanan</option><option value="weekly">Mingguan</option><option value="biweekly">Dua mingguan</option><option value="custom">Kustom</option></select></div>
          <div><label class="label">Mulai</label><input name="starts_on" type="date" required class="input"></div>
          <div><label class="label">Selesai</label><input name="ends_on" type="date" required class="input"></div>
        </div>
        <button class="btn btn-primary">Buat</button>
      </form>
    </details>
  </div>
</div>

<div class="card p-5">
  <div class="flex items-center justify-between mb-3 flex-wrap gap-3">
    <h2 class="font-bold">Riwayat Penggajian (Run)</h2>
    <form method="POST" action="{{ route('admin.payroll.runs.store') }}" class="flex gap-2 items-end">@csrf
      <div><label class="label">Periode</label><select name="payroll_period_id" class="input">@foreach(\App\Models\PayrollPeriod::orderByDesc('starts_on')->get() as $per)<option value="{{ $per->id }}">{{ $per->name }}</option>@endforeach</select></div>
      <button class="btn btn-primary">+ Run Baru</button>
    </form>
  </div>
  <table class="data">
    <thead><tr><th>Periode</th><th>Status</th><th>Dibuat</th><th></th></tr></thead>
    <tbody>
    @foreach($runs as $run)
      <tr><td class="font-semibold">{{ $run->period->name }}</td>
      <td><span class="badge {{ $run->status==='locked'?'bg-gray-200 text-gray-700':($run->status==='finalized'?'bg-emerald-100 text-emerald-800':($run->status==='review'?'bg-amber-100 text-amber-800':'bg-sky-100 text-sky-800')) }}">{{ $run->status }}</span></td>
      <td class="text-xs text-gray-500">{{ $run->created_at->format('d M Y H:i') }}</td>
      <td><a href="{{ route('admin.payroll.runs.show',$run) }}" class="text-emerald-700 font-semibold hover:underline text-sm">Kelola →</a></td></tr>
    @endforeach
    </tbody>
  </table>
  {{ $runs->links() }}
</div>
@endsection
