<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Masuk — Admin AbsensiKu</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
<div class="w-full max-w-sm bg-white rounded-2xl shadow-lg p-8">
  <div class="text-2xl font-extrabold text-emerald-700 mb-1">AbsensiKu</div>
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
    <button class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 rounded-xl transition">Masuk</button>
  </form>
</div>
</body>
</html>
