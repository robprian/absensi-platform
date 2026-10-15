<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'Admin') — {{ config('app.name') }}</title>
@php
  $brandFavicon = \App\Models\SystemSetting::get('brand_favicon_path', '');
  $brandLogo = \App\Models\SystemSetting::get('brand_logo_path', '');
  $brandColor = \App\Models\SystemSetting::get('brand_primary_color', '#059669');
@endphp
@if($brandFavicon)
<link rel="icon" href="{{ Storage::url($brandFavicon) }}">
@endif
<meta name="theme-color" content="{{ $brandColor }}">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = { theme: { extend: { colors: { primary: {50:'#ecfdf5',100:'#d1fae5',500:'#10b981',600:'#059669',700:'#047857'} } } } }
</script>
<style>
  :root { --brand-primary: {{ $brandColor }}; }
  body { font-family: 'Inter', system-ui, sans-serif; }
  .navlink { display:flex; align-items:center; gap:.75rem; padding:.6rem .9rem; border-radius:.65rem; color:#374151; font-size:.9rem; font-weight:500; transition: all .2s ease; }
  .navlink:hover { background:#ecfdf5; color:#047857; transform: translateX(2px); }
  .navlink.active { background:var(--brand-primary); color:#fff; box-shadow: 0 2px 8px rgba(0,0,0,.12); }
  .card { background:#fff; border-radius:1rem; box-shadow:0 1px 3px rgba(0,0,0,.08); transition: box-shadow .2s ease, transform .2s ease; }
  .card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.1); }
  .btn { display:inline-flex; align-items:center; gap:.4rem; padding:.55rem 1rem; border-radius:.65rem; font-weight:600; font-size:.85rem; transition:all .15s; }
  .btn-primary { background:var(--brand-primary); color:#fff; } .btn-primary:hover { filter: brightness(0.92); transform: translateY(-1px); }
  .btn-danger { background:#dc2626; color:#fff; } .btn-danger:hover { background:#b91c1c; }
  .btn-ghost { background:#f3f4f6; color:#374151; } .btn-ghost:hover { background:#e5e7eb; }
  .input { width:100%; border:1px solid #d1d5db; border-radius:.65rem; padding:.55rem .8rem; font-size:.9rem; transition: border-color .15s, box-shadow .15s; }
  .input:focus { outline:none; border-color:var(--brand-primary); box-shadow:0 0 0 3px rgba(5,150,105,.15); }
  .label { display:block; font-size:.8rem; font-weight:600; color:#374151; margin-bottom:.3rem; }
  .badge { display:inline-flex; padding:.15rem .6rem; border-radius:9999px; font-size:.72rem; font-weight:600; }
  table.data { width:100%; font-size:.85rem; } table.data th { text-align:left; padding:.6rem .75rem; color:#6b7280; font-weight:600; border-bottom:2px solid #f3f4f6; white-space:nowrap; }
  table.data td { padding:.65rem .75rem; border-bottom:1px solid #f3f4f6; vertical-align:middle; }
  table.data tr:hover td { background:#f9fafb; }
  /* Smooth page transitions */
  main { animation: fadeIn .3s ease; }
  @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
</style>
</head>
<body class="bg-gray-50 text-gray-900">
<div class="flex min-h-screen">
  <aside class="w-64 shrink-0 bg-white border-r border-gray-100 p-4 hidden md:flex md:flex-col gap-1 sticky top-0 h-screen overflow-y-auto">
    <div class="px-2 py-3 mb-2 flex items-center gap-3">
      @if($brandLogo)
        <img src="{{ Storage::url($brandLogo) }}" alt="Logo" class="h-10 w-10 object-contain rounded-lg">
      @else
        <div class="flex h-10 w-10 items-center justify-center rounded-xl text-white font-extrabold text-lg" style="background: var(--brand-primary)">A</div>
      @endif
      <div>
        <div class="text-lg font-extrabold" style="color: var(--brand-primary)">{{ \App\Models\SystemSetting::get('app_name', 'AbsensiKu') }}</div>
        <div class="text-xs text-gray-500">Portal Admin</div>
      </div>
    </div>
    @php $r = request()->route()->getName(); @endphp
    <a href="{{ route('admin.dashboard') }}" class="navlink {{ str_starts_with($r,'admin.dashboard')?'active':'' }}">📊 Dashboard</a>
    <a href="{{ route('admin.employees.index') }}" class="navlink {{ str_starts_with($r,'admin.employees')?'active':'' }}">👥 Karyawan</a>
    <a href="{{ route('admin.attendance.index') }}" class="navlink {{ str_starts_with($r,'admin.attendance')?'active':'' }}">🕒 Absensi</a>
    <a href="{{ route('admin.sites.index') }}" class="navlink {{ str_starts_with($r,'admin.sites')||str_starts_with($r,'admin.schedules')?'active':'' }}">📅 Jadwal</a>
    <a href="{{ route('admin.payroll.index') }}" class="navlink {{ str_starts_with($r,'admin.payroll')?'active':'' }}">💰 Penggajian</a>
    <a href="{{ route('admin.reimbursements.index') }}" class="navlink {{ str_starts_with($r,'admin.reimbursements')?'active':'' }}">🧾 Reimburse</a>
    <a href="{{ route('admin.reports.index') }}" class="navlink {{ str_starts_with($r,'admin.reports')?'active':'' }}">📈 Laporan</a>
    <a href="{{ route('admin.documents.index') }}" class="navlink {{ str_starts_with($r,'admin.documents')?'active':'' }}">📁 Dokumen</a>
    <a href="{{ route('admin.settings.index') }}" class="navlink {{ str_starts_with($r,'admin.settings')?'active':'' }}">⚙️ Pengaturan</a>
    <a href="{{ route('admin.settings.branding') }}" class="navlink {{ str_starts_with($r,'admin.settings.branding')?'active':'' }}">🎨 Branding</a>
    <a href="{{ route('admin.audit.index') }}" class="navlink {{ str_starts_with($r,'admin.audit')?'active':'' }}">📝 Audit Log</a>
    <div class="mt-auto pt-4 border-t border-gray-100">
      <div class="px-2 text-sm font-semibold">{{ auth()->user()->name }}</div>
      <div class="px-2 text-xs text-gray-500 mb-2">{{ auth()->user()->roleNames()[0] ?? '' }}</div>
      <form method="POST" action="{{ route('admin.logout') }}">@csrf
        <button class="navlink w-full text-red-600">🚪 Keluar</button>
      </form>
    </div>
  </aside>
  <main class="flex-1 p-4 md:p-8 max-w-7xl w-full">
    <div class="md:hidden mb-4 flex items-center justify-between">
      <div class="text-lg font-extrabold text-primary-700">AbsensiKu Admin</div>
      <form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="btn btn-ghost text-sm">Keluar</button></form>
    </div>
    {{-- Navigasi mobile: sidebar disembunyikan di layar kecil --}}
    <nav class="md:hidden mb-4 -mx-4 px-4 overflow-x-auto">
      <div class="flex gap-2 pb-2 whitespace-nowrap">
        <a href="{{ route('admin.dashboard') }}" class="navlink {{ str_starts_with($r,'admin.dashboard')?'active':'' }}">📊 Dashboard</a>
        <a href="{{ route('admin.employees.index') }}" class="navlink {{ str_starts_with($r,'admin.employees')?'active':'' }}">👥 Karyawan</a>
        <a href="{{ route('admin.attendance.index') }}" class="navlink {{ str_starts_with($r,'admin.attendance')?'active':'' }}">🕒 Absensi</a>
        <a href="{{ route('admin.sites.index') }}" class="navlink {{ str_starts_with($r,'admin.sites')||str_starts_with($r,'admin.schedules')?'active':'' }}">📅 Jadwal & Lokasi</a>
        <a href="{{ route('admin.payroll.index') }}" class="navlink {{ str_starts_with($r,'admin.payroll')?'active':'' }}">💰 Penggajian</a>
        <a href="{{ route('admin.reimbursements.index') }}" class="navlink {{ str_starts_with($r,'admin.reimbursements')?'active':'' }}">🧾 Reimburse</a>
        <a href="{{ route('admin.reports.index') }}" class="navlink {{ str_starts_with($r,'admin.reports')?'active':'' }}">📈 Laporan</a>
        <a href="{{ route('admin.documents.index') }}" class="navlink {{ str_starts_with($r,'admin.documents')?'active':'' }}">📁 Dokumen</a>
        <a href="{{ route('admin.settings.index') }}" class="navlink {{ str_starts_with($r,'admin.settings')?'active':'' }}">⚙️ Pengaturan</a>
        <a href="{{ route('admin.settings.branding') }}" class="navlink {{ str_starts_with($r,'admin.settings.branding')?'active':'' }}">🎨 Branding</a>
        <a href="{{ route('admin.audit.index') }}" class="navlink {{ str_starts_with($r,'admin.audit')?'active':'' }}">📝 Audit</a>
      </div>
    </nav>
    @if(session('ok'))<div class="mb-4 p-3 rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('ok') }}</div>@endif
    @if(session('err'))<div class="mb-4 p-3 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm">{{ session('err') }}</div>@endif
    @if($errors->any())<div class="mb-4 p-3 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
    @yield('content')
  </main>
</div>
</body>
</html>
