@extends('layouts.admin')
@section('title','Jadwal & Lokasi')

@section('content')
<h1 class="text-2xl font-extrabold mb-6">Jadwal & Lokasi Kerja</h1>

<div class="grid lg:grid-cols-2 gap-6 mb-8">
  <div class="card p-5">
    <h2 class="font-bold mb-3">Lokasi Kerja (Geofence)</h2>
    <table class="data mb-4">
      <thead><tr><th>Nama</th><th>Radius</th><th>Geofence</th><th></th></tr></thead>
      <tbody>
      @foreach($sites as $s)
        <tr><td class="font-semibold">{{ $s->name }}<div class="text-xs text-gray-400 font-normal">{{ $s->latitude }}, {{ $s->longitude }}</div></td>
        <td>{{ $s->radius_meters }} m</td><td>{{ $s->geofence_enabled?'✅':'❌' }}</td>
        <td><form method="POST" action="{{ route('admin.sites.destroy',$s) }}" onsubmit="return confirm('Hapus lokasi ini?')">@csrf @method('DELETE')<button class="text-red-600 text-sm font-semibold hover:underline">Hapus</button></form></td></tr>
      @endforeach
      </tbody>
    </table>
    <details class="text-sm"><summary class="cursor-pointer font-semibold text-emerald-700">+ Tambah lokasi</summary>
      <form method="POST" action="{{ route('admin.sites.store') }}" class="mt-3 space-y-3">@csrf
        <div><label class="label">Nama lokasi</label><input name="name" required class="input"></div>
        <div><label class="label">Alamat</label><input name="address" class="input"></div>
        <div class="grid grid-cols-3 gap-3">
          <div><label class="label">Latitude</label><input name="latitude" type="number" step="any" required class="input" placeholder="-6.2088"></div>
          <div><label class="label">Longitude</label><input name="longitude" type="number" step="any" required class="input" placeholder="106.8456"></div>
          <div><label class="label">Radius (m)</label><input name="radius_meters" type="number" min="10" value="100" class="input"></div>
        </div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="geofence_enabled" value="1" checked class="w-4 h-4"> Aktifkan geofence</label>
        <button class="btn btn-primary">Simpan</button>
      </form>
    </details>
  </div>

  <div class="card p-5">
    <h2 class="font-bold mb-3">Penugasan Karyawan</h2>
    <table class="data mb-4">
      <thead><tr><th>Karyawan</th><th>Lokasi</th><th>Sejak</th></tr></thead>
      <tbody>
      @foreach($assignments as $a)
        <tr><td class="font-semibold">{{ $a->employee->name }}</td><td>{{ $a->workSite->name }}</td><td class="text-xs">{{ $a->assigned_at->format('d M Y') }}</td></tr>
      @endforeach
      </tbody>
    </table>
    <details class="text-sm"><summary class="cursor-pointer font-semibold text-emerald-700">Ubah penugasan</summary>
      <form method="POST" action="{{ route('admin.sites.assign') }}" class="mt-3 space-y-3">@csrf
        <div><label class="label">Karyawan</label><select name="employee_id" class="input">@foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>@endforeach</select></div>
        <div><label class="label">Lokasi (kosongkan = tanpa batasan lokasi)</label><select name="work_site_id" class="input"><option value="">— Tanpa batasan —</option>@foreach($sites as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div>
        <button class="btn btn-primary">Simpan</button>
      </form>
    </details>
  </div>
</div>

<div class="card p-5">
  <h2 class="font-bold mb-3">Jam Kerja / Shift</h2>
  <table class="data mb-4">
    <thead><tr><th>Hari</th><th>Karyawan</th><th>Shift</th><th>Jam</th><th>Istirahat</th><th>Lembur?</th></tr></thead>
    <tbody>
    @php $days=['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu']; @endphp
    @foreach($schedules as $sc)
      <tr><td>{{ $days[$sc->day_of_week] }}</td><td>{{ $sc->employee?->name ?? 'Default semua' }}</td><td>{{ $sc->shift_name ?? '—' }}</td>
      <td class="whitespace-nowrap">{{ substr($sc->start_time,0,5) }} – {{ substr($sc->end_time,0,5) }}{{ $sc->overnight?' 🌙':'' }}</td>
      <td>{{ $sc->break_minutes }} mnt</td><td>{{ $sc->active?'✅':'❌' }}</td></tr>
    @endforeach
    </tbody>
  </table>
  <details class="text-sm"><summary class="cursor-pointer font-semibold text-emerald-700">+ Tambah/ubah jadwal</summary>
    <form method="POST" action="{{ route('admin.schedules.store') }}" class="mt-3 grid md:grid-cols-3 gap-3">@csrf
      <div><label class="label">Hari</label><select name="day_of_week" class="input">@foreach($days as $i=>$d)<option value="{{ $i }}">{{ $d }}</option>@endforeach</select></div>
      <div><label class="label">Karyawan (kosong = default)</label><select name="employee_id" class="input"><option value="">Default semua</option>@foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>@endforeach</select></div>
      <div><label class="label">Nama shift</label><input name="shift_name" class="input" placeholder="Pagi"></div>
      <div><label class="label">Mulai</label><input name="start_time" type="time" required class="input" value="08:00"></div>
      <div><label class="label">Selesai</label><input name="end_time" type="time" required class="input" value="17:00"></div>
      <div><label class="label">Istirahat (mnt)</label><input name="break_minutes" type="number" min="0" value="60" class="input"></div>
      <div class="md:col-span-3"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="overnight" value="1" class="w-4 h-4"> Shift malam (lewat tengah malam)</label></div>
      <div class="md:col-span-3"><button class="btn btn-primary">Simpan</button></div>
    </form>
  </details>
</div>
@endsection
