@extends('layouts.admin')
@section('title','Audit Log')

@section('content')
<h1 class="text-2xl font-extrabold mb-6">Audit Log</h1>

<div class="card p-4 mb-4">
  <form method="GET" class="flex gap-3">
    <input name="action" value="{{ request('action') }}" placeholder="Cari aksi…" class="input max-w-xs">
    <button class="btn btn-ghost">Filter</button>
  </form>
</div>

<div class="card p-2 overflow-x-auto">
<table class="data">
  <thead><tr><th>Waktu</th><th>Aksi</th><th>Pelaku</th><th>Entitas</th><th>IP</th></tr></thead>
  <tbody>
  @foreach($logs as $l)
    <tr>
      <td class="whitespace-nowrap text-xs">{{ $l->created_at->format('d M Y H:i:s') }}</td>
      <td class="font-mono text-xs">{{ $l->action }}</td>
      <td>{{ $l->actor?->name ?? '—' }}</td>
      <td class="text-xs">{{ $l->entity_type }} #{{ $l->entity_id }}</td>
      <td class="text-xs text-gray-400">{{ $l->ip_address }}</td>
    </tr>
  @endforeach
  </tbody>
</table>
</div>
<div class="mt-4">{{ $logs->links() }}</div>
@endsection
