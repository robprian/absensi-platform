<script lang="ts">
	import { goto } from '$app/navigation';
	import { ChevronLeft, Smartphone, Share, PlusSquare, Download } from 'lucide-svelte';
	import { installPromptEvent, isStandalone } from '$lib/settings';

	let installing = false;
	let installError = '';

	async function pasang() {
		const evt = $installPromptEvent as
			(Event & { prompt: () => Promise<void>; userChoice: Promise<{ outcome: string }> }) | null;
		if (!evt) return;
		installing = true;
		try {
			await evt.prompt();
			await evt.userChoice;
		} catch {
			installError = 'Gagal memasang. Ikuti panduan manual di bawah.';
		} finally {
			installing = false;
		}
	}

	$: canPrompt = !!$installPromptEvent;
	$: standalone = $isStandalone;
</script>

<svelte:head>
	<title>Pasang Aplikasi — Absensi Kerja</title>
</svelte:head>

<div class="px-5 pb-6 pt-5">
	<div class="flex items-center gap-2">
		<button
			type="button"
			on:click={() => goto('/profil')}
			class="pressable flex h-11 w-11 items-center justify-center rounded-xl bg-white shadow-sm"
			aria-label="Kembali"
		>
			<ChevronLeft size={22} class="text-slate-600" />
		</button>
		<h1 class="text-2xl font-extrabold">Pasang Aplikasi</h1>
	</div>

	{#if standalone}
		<div class="mt-4 rounded-2xl bg-emerald-50 p-5 text-center">
			<p class="font-bold text-emerald-800">Aplikasi sudah terpasang 🎉</p>
			<p class="mt-1 text-sm text-emerald-700">Anda membukanya dalam mode aplikasi.</p>
		</div>
	{:else}
		<p class="mt-3 text-slate-500">
			Pasang <strong>Absensi Kerja</strong> di layar utama agar cepat dibuka seperti aplikasi biasa.
		</p>

		{#if canPrompt}
			<button
				type="button"
				on:click={pasang}
				disabled={installing}
				class="pressable mt-5 flex min-h-[60px] w-full items-center justify-center gap-2 rounded-2xl bg-emerald-600 text-lg font-bold text-white shadow-lg disabled:opacity-60"
			>
				<Download size={22} />
				{installing ? 'Memasang…' : 'Pasang Sekarang'}
			</button>
			{#if installError}
				<p class="mt-2 text-sm text-red-600">{installError}</p>
			{/if}
		{/if}

		<div class="mt-6 space-y-4">
			<section class="rounded-2xl bg-white p-5 shadow-sm">
				<h2 class="flex items-center gap-2 font-bold">
					<Smartphone size={20} class="text-emerald-600" /> Android (Chrome)
				</h2>
				<ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-slate-600">
					<li>Ketuk ikon titik tiga (⋮) di kanan atas.</li>
					<li>
						Pilih <strong>“Tambahkan ke Layar utama”</strong> / <strong>“Install aplikasi”</strong>.
					</li>
					<li>Ketuk <strong>Pasang</strong>.</li>
				</ol>
			</section>
			<section class="rounded-2xl bg-white p-5 shadow-sm">
				<h2 class="flex items-center gap-2 font-bold">
					<Share size={20} class="text-sky-600" /> iPhone (Safari)
				</h2>
				<ol class="mt-2 list-decimal space-y-1 pl-5 text-sm text-slate-600">
					<li>Ketuk tombol <strong>Bagikan</strong> (kotak + panah) di bawah.</li>
					<li>Gulir dan pilih <strong>“Tambah ke Layar Utama”</strong>.</li>
					<li>Ketuk <strong>Tambah</strong> di kanan atas.</li>
				</ol>
			</section>
			<section class="rounded-2xl bg-white p-5 shadow-sm">
				<h2 class="flex items-center gap-2 font-bold">
					<PlusSquare size={20} class="text-amber-600" /> Catatan
				</h2>
				<p class="mt-2 text-sm text-slate-600">
					Kamera dan GPS membutuhkan izin browser dan koneksi aman (HTTPS). Fitur absen tetap
					berfungsi penuh setelah aplikasi terpasang.
				</p>
			</section>
		</div>
	{/if}
</div>
