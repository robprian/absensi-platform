@extends('layouts.admin')
@section('title', $employee->exists ? 'Ubah Karyawan' : 'Tambah Karyawan')

@section('content')
<h1 class="text-2xl font-extrabold mb-6">{{ $employee->exists ? 'Ubah Karyawan' : 'Tambah Karyawan' }}</h1>

<div class="card p-6 max-w-2xl">
<form method="POST" action="{{ $employee->exists ? route('admin.employees.update',$employee) : route('admin.employees.store') }}" class="space-y-4">
  @csrf
  @if($employee->exists) @method('PUT') @endif

  <div class="grid md:grid-cols-2 gap-4">
    <div><label class="label">Nama Lengkap *</label><input name="name" value="{{ old('name',$employee->name) }}" required class="input"></div>
    <div><label class="label">Telepon</label><input name="phone" value="{{ old('phone',$employee->phone) }}" class="input"></div>
    <div><label class="label">Email</label><input name="email" type="email" value="{{ old('email',$employee->email) }}" class="input"></div>
    <div><label class="label">Jabatan</label><input name="position" value="{{ old('position',$employee->position) }}" class="input"></div>
    <div><label class="label">Departemen</label>
      <select name="department_id" class="input"><option value="">—</option>
      @foreach($departments as $d)<option value="{{ $d->id }}" @selected(old('department_id',$employee->department_id)==$d->id)>{{ $d->name }}</option>@endforeach
      </select></div>
    <div><label class="label">Tanggal Masuk</label><input name="hire_date" type="date" value="{{ old('hire_date',$employee->hire_date?->format('Y-m-d')) }}" class="input"></div>
    @if($employee->exists)
    <div><label class="label">Status</label>
      <select name="employment_status" class="input">
      @foreach(['aktif','nonaktif','keluar'] as $st)<option value="{{ $st }}" @selected(old('employment_status',$employee->employment_status)===$st)>{{ ucfirst($st) }}</option>@endforeach
      </select></div>
    @endif
  </div>
  <div><label class="label">Catatan</label><textarea name="notes" rows="2" class="input">{{ old('notes',$employee->notes) }}</textarea></div>

  @if(!$employee->exists)
  <div class="border-t pt-4">
    <label class="flex items-center gap-2 text-sm font-semibold mb-3"><input type="checkbox" name="create_login" value="1" class="w-4 h-4" checked> Buatkan akun login (untuk aplikasi karyawan)</label>
    <div class="grid md:grid-cols-2 gap-4">
      <div><label class="label">Email Login</label><input name="login_email" type="email" class="input" placeholder="nama@example.com"></div>
      <div><label class="label">Kata Sandi (min. 8)</label><input name="login_password" type="password" class="input"></div>
    </div>
  </div>
  @endif

  <div class="flex gap-3 pt-2">
    <button class="btn btn-primary">Simpan</button>
    <a href="{{ route('admin.employees.index') }}" class="btn btn-ghost">Batal</a>
    @if($employee->exists && $employee->employment_status==='aktif')
      <button type="submit" form="deactivate-form" class="btn btn-danger ml-auto" onclick="return confirm('Nonaktifkan karyawan ini? Akun login akan dicabut.')">Nonaktifkan</button>
    @endif
  </div>
</form>
@if($employee->exists && $employee->employment_status==='aktif')
<form id="deactivate-form" method="POST" action="{{ route('admin.employees.deactivate',$employee) }}">@csrf</form>
@endif
</div>
@endsection
