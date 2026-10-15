@extends('layouts.admin')

@section('title', 'Branding')

@section('content')
<div class="max-w-4xl">
  <h1 class="text-2xl font-extrabold mb-1">🎨 Branding</h1>
  <p class="text-sm text-gray-500 mb-6">Atur logo perusahaan, ikon PWA, dan favicon web. Perubahan langsung tampil di aplikasi karyawan & portal admin.</p>

  @if (session('ok'))
    <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-800">{{ session('ok') }}</div>
  @endif
  @if ($errors->any())
    <div class="mb-4 rounded-xl bg-red-50 border border-red-200 p-4 text-sm text-red-800">
      <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  @php
    $items = [
      'brand_logo_path' => ['label' => 'Logo Perusahaan', 'desc' => 'Tampil di header admin & halaman login. PNG/SVG, maks 2MB.', 'preview' => 'h-16'],
      'brand_icon_192_path' => ['label' => 'Ikon PWA 192×192', 'desc' => 'Ikon aplikasi saat di-install di HP. PNG 192×192, maks 2MB.', 'preview' => 'h-20 w-20'],
      'brand_icon_512_path' => ['label' => 'Ikon PWA 512×512', 'desc' => 'Ikon resolusi tinggi untuk splash screen. PNG 512×512, maks 2MB.', 'preview' => 'h-20 w-20'],
      'brand_favicon_path' => ['label' => 'Favicon Web', 'desc' => 'Ikon kecil di tab browser. PNG/ICO 32×32 atau 48×48.', 'preview' => 'h-10 w-10'],
    ];
  @endphp

  <div class="grid gap-4 md:grid-cols-2">
    @foreach ($items as $key => $meta)
      @php $setting = $settings[$key] ?? null; $path = $setting?->value; @endphp
      <div class="rounded-2xl bg-white border border-gray-100 p-5 shadow-sm">
        <h3 class="font-bold text-gray-800">{{ $meta['label'] }}</h3>
        <p class="text-xs text-gray-500 mt-1 mb-4">{{ $meta['desc'] }}</p>

        <div class="flex items-center gap-4 mb-4">
          @if ($path)
            <img src="{{ Storage::url($path) }}" alt="{{ $meta['label'] }}" class="{{ $meta['preview'] }} object-contain rounded-lg bg-gray-50 border border-gray-100 p-1">
            <span class="text-xs text-emerald-600 font-semibold">✓ Terpasang</span>
          @else
            <div class="{{ $meta['preview'] }} flex items-center justify-center rounded-lg bg-gray-100 text-gray-400 text-xs">Belum ada</div>
            <span class="text-xs text-gray-400">Pakai default</span>
          @endif
        </div>

        <form method="POST" action="{{ route('admin.settings.branding.upload') }}" enctype="multipart/form-data" class="flex flex-col gap-2">
          @csrf
          <input type="hidden" name="key" value="{{ $key }}">
          <input type="file" name="file" accept="image/png,image/jpeg,image/webp,image/svg+xml,image/x-icon" required
            class="text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-600 file:px-4 file:py-2 file:text-sm file:font-bold file:text-white hover:file:bg-emerald-700">
          <button class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-700">Unggah</button>
        </form>

        @if ($path)
          <form method="POST" action="{{ route('admin.settings.branding.delete') }}" class="mt-2" onsubmit="return confirm('Hapus file ini?')">
            @csrf @method('DELETE')
            <input type="hidden" name="key" value="{{ $key }}">
            <button class="text-xs text-red-600 hover:underline">Hapus & kembali ke default</button>
          </form>
        @endif
      </div>
    @endforeach
  </div>

  <div class="mt-6 rounded-2xl bg-white border border-gray-100 p-5 shadow-sm">
    <h3 class="font-bold text-gray-800 mb-2">Warna Utama Tema</h3>
    <p class="text-xs text-gray-500 mb-4">Warna aksen untuk tombol & header di PWA dan portal admin.</p>
    <form method="POST" action="{{ route('admin.settings.update') }}" class="flex items-center gap-3">
      @csrf @method('PUT')
      @php $color = $settings['brand_primary_color']?->value ?? '#059669'; @endphp
      <input type="hidden" name="settings[0][key]" value="brand_primary_color">
      <input type="color" name="settings[0][value]" value="{{ $color }}" class="h-12 w-20 rounded-lg border border-gray-200 cursor-pointer">
      <span class="text-sm font-mono text-gray-600">{{ $color }}</span>
      <button class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700">Simpan</button>
    </form>
  </div>
</div>
@endsection
