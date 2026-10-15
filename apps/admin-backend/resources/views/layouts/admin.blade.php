<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'Admin') — {{ config('app.name') }}</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = { theme: { extend: { colors: { primary: {50:'#ecfdf5',100:'#d1fae5',500:'#10b981',600:'#059669',700:'#047857'} } } } }
</script>
<style>
  body { font-family: 'Inter', system-ui, sans-serif; }
  .navlink { display:flex; align-items:center; gap:.75rem; padding:.6rem .9rem; border-radius:.65rem; color:#374151; font-size:.9rem; font-weight:500; }
  .navlink:hover { background:#ecfdf5; color:#047857; }
  .navlink.active { background:#059669; color:#fff; }
  .card { background:#fff; border-radius:1rem; box-shadow:0 1px 3px rgba(0,0,0,.08); }
  .btn { display:inline-flex; align-items:center; gap:.4rem; padding:.55rem 1rem; border-radius:.65rem; font-weight:600; font-size:.85rem; transition:all .15s; }
  .btn-primary { background:#059669; color:#fff; } .btn-primary:hover { background:#047857; }
  .btn-danger { background:#dc2626; color:#fff; } .btn-danger:hover { background:#b91c1c; }
  .btn-ghost { background:#f3f4f6; color:#374151; } .btn-ghost:hover { background:#e5e7eb; }
  .input { width:100%; border:1px solid #d1d5db; border-radius:.65rem; padding:.55rem .8rem; font-size:.9rem; }
  .input:focus { outline:none; border-color:#059669; box-shadow:0 0 0 3px #d1fae5; }
  .label { display:block; font-size:.8rem; font-weight:600; color:#374151; margin-bottom:.3rem; }
  .badge { display:inline-flex; padding:.15rem .6rem; border-radius:9999px; font-size:.72rem; font-weight:600; }
  table.data { width:100%; font-size:.85rem; } table.data th { text-align:left; padding:.6rem .75rem; color:#6b7280; font-weight:600; border-bottom:2px solid #f3f4f6; white-space:nowrap; }
  table.data td { padding:.65rem .75rem; border-bottom:1px solid #f3f4f6; vertical-align:middle; }
  table.data tr:hover td { background:#f9fafb; }
</style>
</head>
<body class="bg-gray-50 text-gray-900">
<div class="flex min-h-screen">
  <aside class="w-64 shrink-0 bg-white border-r border-gray-100 p-4 hidden md:flex md:flex-col gap-1 sticky top-0 h-screen overflow-y-auto">
    <div class="px-2 py-3 mb-2">
      <div class="text-lg font-extrabold text-primary-700">AbsensiKu</div>
      <div class="text-xs text-gray-500">Portal Admin</div>
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
    @if(session('ok'))<div class="mb-4 p-3 rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('ok') }}</div>@endif
    @if(session('err'))<div class="mb-4 p-3 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm">{{ session('err') }}</div>@endif
    @if($errors->any())<div class="mb-4 p-3 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
    @yield('content')
  </main>
</div>
</body>
</html>
