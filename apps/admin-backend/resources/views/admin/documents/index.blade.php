@extends('layouts.admin')
@section('title','Dokumen')

@section('content')
<div class="flex items-center justify-between mb-6 flex-wrap gap-3">
  <h1 class="text-2xl font-extrabold">Dokumen & Penyimpanan</h1>
  <a href="{{ route('admin.documents.tdocs-health') }}" class="btn btn-ghost">🩺 Cek Kesehatan tDocs</a>
</div>

<div class="card p-2 overflow-x-auto">
<table class="data">
  <thead><tr><th>Nama File</th><th>Folder</th><th>Ukuran</th><th>tDocs ID</th><th>Status</th><th>Diunggah</th></tr></thead>
  <tbody>
  @foreach($files as $f)
    <tr>
      <td class="font-semibold text-sm">{{ $f->original_name }}<div class="text-xs text-gray-400 font-normal">{{ $f->mime_type }}</div></td>
      <td><span class="badge bg-gray-100">{{ $f->folder }}</span></td>
      <td class="text-xs whitespace-nowrap">{{ number_format($f->size_bytes/1024,1) }} KB</td>
      <td class="font-mono text-xs">{{ $f->tdocs_file_id ?? '—' }}</td>
      <td><span class="badge {{ $f->status==='uploaded'?'bg-emerald-100 text-emerald-800':($f->status==='pending'?'bg-amber-100 text-amber-800':'bg-red-100 text-red-800') }}">{{ $f->status }}</span>
        @if($f->last_error)<div class="text-xs text-red-500 max-w-[250px]">{{ $f->last_error }}</div>@endif</td>
      <td class="text-xs text-gray-500 whitespace-nowrap">{{ $f->created_at->format('d M Y H:i') }}</td>
    </tr>
  @endforeach
  </tbody>
</table>
</div>
<div class="mt-4">{{ $files->links() }}</div>
@endsection
