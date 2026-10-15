@extends('layouts.admin')
@section('title','Karyawan')

@section('content')
<div class="flex items-center justify-between mb-6">
  <h1 class="text-2xl font-extrabold">Karyawan</h1>
  <a href="{{ route('admin.employees.create') }}" class="btn btn-primary">+ Tambah Karyawan</a>
</div>

<div class="card p-4 mb-4">
  <form method="GET" class="flex flex-wrap gap-3">
    <input name="search" value="{{ request('search') }}" placeholder="Cari nama / kode…" class="input max-w-xs">
    <select name="status" class="input max-w-[200px]">
      <option value="">Semua status</option>
      @foreach(['aktif','nonaktif','keluar'] as $st)
        <option value="{{ $st }}" @selected(request('status')===$st)>{{ ucfirst($st) }}</option>
      @endforeach
    </select>
    <button class="btn btn-ghost">Filter</button>
  </form>
</div>

<div class="card p-2 overflow-x-auto">
<table class="data">
  <thead><tr><th>Kode</th><th>Nama</th><th>Jabatan</th><th>Departemen</th><th>Telepon</th><th>Status</th><th></th></tr></thead>
  <tbody>
  @foreach($employees as $e)
    <tr>
      <td class="font-mono text-xs">{{ $e->employee_code }}</td>
      <td class="font-semibold">{{ $e->name }}@if($e->user)<div class="text-xs text-gray-400">🔑 ada akun login</div>@endif</td>
      <td>{{ $e->position ?? '—' }}</td>
      <td>{{ $e->department?->name ?? '—' }}</td>
      <td>{{ $e->phone ?? '—' }}</td>
      <td><span class="badge {{ $e->employment_status==='aktif'?'bg-emerald-100 text-emerald-800':'bg-gray-100 text-gray-600' }}">{{ $e->employment_status }}</span></td>
      <td class="whitespace-nowrap"><a href="{{ route('admin.employees.edit',$e) }}" class="text-emerald-700 font-semibold hover:underline text-sm">Ubah</a></td>
    </tr>
  @endforeach
  </tbody>
</table>
</div>
<div class="mt-4">{{ $employees->links() }}</div>
@endsection
