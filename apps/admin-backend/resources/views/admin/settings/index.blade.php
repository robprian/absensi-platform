@extends('layouts.admin')
@section('title','Pengaturan')

@section('content')
<h1 class="text-2xl font-extrabold mb-6">Pengaturan Aplikasi</h1>

<form method="POST" action="{{ route('admin.settings.update') }}">
@csrf @method('PUT')
@php $idx = 0; @endphp
@foreach($grouped as $group => $settings)
<div class="card p-5 mb-5">
  <h2 class="font-bold mb-4 capitalize">{{ $group }}</h2>
  <div class="grid md:grid-cols-2 gap-4">
  @foreach($settings as $s)
    <div>
      <label class="label">{{ $s->description ?? $s->key }} <span class="text-gray-400 font-normal">({{ $s->key }})</span></label>
      @if($s->value_type==='boolean')
        <select name="settings[{{ $idx }}][value]" class="input">
          <option value="1" @selected($s->value==='1')>Aktif</option>
          <option value="0" @selected($s->value!=='1')>Nonaktif</option>
        </select>
      @elseif($s->key==='gps_policy')
        <select name="settings[{{ $idx }}][value]" class="input">
          <option value="enforce" @selected($s->value==='enforce')>enforce — tolak di luar radius</option>
          <option value="warn" @selected($s->value==='warn')>warn — catat tapi tandai</option>
          <option value="off" @selected($s->value==='off')>off — nonaktif</option>
        </select>
      @else
        <input name="settings[{{ $idx }}][value]" value="{{ $s->value }}" class="input">
      @endif
      <input type="hidden" name="settings[{{ $idx }}][key]" value="{{ $s->key }}">
      @php $idx++; @endphp
    </div>
  @endforeach
  </div>
</div>
@endforeach
<button class="btn btn-primary">💾 Simpan Semua Pengaturan</button>
</form>
@endsection
