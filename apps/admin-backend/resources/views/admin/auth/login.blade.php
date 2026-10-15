<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
@php
  $appName = \App\Models\SystemSetting::get('app_name', 'AbsensiKu');
  $companyName = \App\Models\SystemSetting::get('company_name', '');
  $brandLogo = \App\Models\SystemSetting::get('brand_logo_path', '');
  $brandFavicon = \App\Models\SystemSetting::get('brand_favicon_path', '');
  $brandColor = \App\Models\SystemSetting::get('brand_primary_color', '#059669');
@endphp
<title>Masuk — Admin {{ $appName }}</title>
@if($brandFavicon)
<link rel="icon" href="{{ Storage::url($brandFavicon) }}">
@endif
<meta name="theme-color" content="{{ $brandColor }}">
<script src="https://cdn.tailwindcss.com"></script>
@if(($turnstile['enabled'] ?? false) && ($turnstile['site_key'] ?? false))
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
@endif
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
<div class="w-full max-w-sm bg-white rounded-2xl shadow-lg p-8">
  <div class="flex items-center gap-3 mb-1">
    @if($brandLogo)
      <img src="{{ Storage::url($brandLogo) }}" alt="Logo" class="h-10 w-10 object-contain rounded-lg">
    @endif
    <div class="text-2xl font-extrabold" style="color: {{ $brandColor }}">{{ $appName }}</div>
  </div>
  @if($companyName)
    <p class="text-xs text-gray-400 mb-1">{{ $companyName }}</p>
  @endif
  <p class="text-sm text-gray-500 mb-6">Masuk ke Portal Admin</p>
  @if($errors->any())
    <div class="mb-4 p-3 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm">{{ $errors->first() }}</div>
  @endif
  <form method="POST" action="{{ route('admin.login') }}" class="space-y-4">
    @csrf
    <div><label class="block text-xs font-semibold text-gray-600 mb-1">Email</label>
      <input type="email" name="email" value="{{ old('email') }}" required class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-200"></div>
    <div><label class="block text-xs font-semibold text-gray-600 mb-1">Kata Sandi</label>
      <input type="password" name="password" required class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-200"></div>
    @if(($turnstile['enabled'] ?? false) && ($turnstile['site_key'] ?? false))
      <div class="cf-turnstile" data-sitekey="{{ $turnstile['site_key'] }}" data-callback="onTurnstileOk"></div>
      <input type="hidden" name="turnstile_token" id="turnstile_token">
      <script>
        function onTurnstileOk(token) {
          document.getElementById('turnstile_token').value = token;
        }
      </script>
    @endif
    <button class="w-full text-white font-bold py-2.5 rounded-xl transition hover:brightness-95" style="background: {{ $brandColor }}">Masuk</button>
  </form>
</div>
</body>
</html>
