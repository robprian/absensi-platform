<script lang="ts">
	import { onMount, onDestroy } from 'svelte';
	import { goto } from '$app/navigation';
	import { Play, Square, History, Receipt, Wallet, MapPin, Clock } from 'lucide-svelte';
	import { api, ApiError, type TodayAttendance } from '$lib/api';
	import { employee } from '$lib/auth';
	import { publicSettings } from '$lib/settings';
	import { formatJam, formatDurasi, formatStopwatch } from '$lib/format';
	import AppHeader from '$lib/components/AppHeader.svelte';
	import Skeleton from '$lib/components/Skeleton.svelte';
	import ErrorCard from '$lib/components/ErrorCard.svelte';

	let today: TodayAttendance | null = null;
	let loading = true;
	let loadError = '';
	let unread = 0;

	// Penghitung live (detik sejak masuk)
	let tick = 0;
	let timer: ReturnType<typeof setInterval> | undefined;
	let now = new Date();
	let clockTimer: ReturnType<typeof setInterval> | undefined;

	function mulaiTimer(masukAt: string) {
		hentikanTimer();
		const awal = new Date(masukAt).getTime();
		const update = () => (tick = Math.max(0, Math.floor((Date.now() - awal) / 1000)));
		update();
		timer = setInterval(update, 1000);
	}
	function hentikanTimer() {
		if (timer) clearInterval(timer);
		timer = undefined;
	}

	async function muat() {
		loading = true;
		loadError = '';
		try {
			const [t, notif] = await Promise.allSettled([api.todayAttendance(), api.notifications()]);
			if (t.status === 'fulfilled') {
				today = t.value;
				if (today.status === 'sedang_bekerja' && today.masuk_at) mulaiTimer(today.masuk_at);
			} else {
				throw t.reason;
			}
			if (notif.status === 'fulfilled') {
				unread = notif.value.items.filter((n) => !n.sudah_dibaca).length;
			}
		} catch (e) {
			hentikanTimer();
			loadError = e instanceof ApiError ? e.message : 'Gagal memuat data kehadiran.';
		} finally {
			loading = false;
		}
	}

	onMount(() => {
		void muat();
		clockTimer = setInterval(() => (now = new Date()), 1000);
		return () => {
			hentikanTimer();
			if (clockTimer) clearInterval(clockTimer);
		};
	});
	onDestroy(() => {
		hentikanTimer();
		if (clockTimer) clearInterval(clockTimer);
	});

	function keAbsen(type: 'MASUK' | 'KELUAR') {
		void goto(`/absen?type=${type}`);
	}

	$: nama = $employee?.nama ?? '';
	$: reimburseOn = $publicSettings?.reimbursement_enabled ?? false;

	$: statusLabel =
		today?.status === 'sedang_bekerja'
			? 'Sedang Bekerja'
			: today?.status === 'selesai_bekerja'
				? 'Selesai Bekerja'
				: 'Belum Absen';
	$: statusColor =
		today?.status === 'sedang_bekerja'
			? 'bg-emerald-100 text-emerald-700'
			: today?.status === 'selesai_bekerja'
				? 'bg-sky-100 text-sky-700'
				: 'bg-slate-100 text-slate-600';
</script>

<svelte:head>
	<title>Beranda — Absensi Kerja</title>
</svelte:head>

<AppHeader {nama} unreadCount={unread} onBell={() => goto('/notifikasi')} />

<div class="px-5 pb-6">
	{#if loading}
		<Skeleton lines={3} />
	{:else if loadError}
		<ErrorCard title="Gagal memuat" message={loadError} onRetry={muat} />
	{:else if today}
		<!-- Kartu kehadiran utama -->
		<section class="card-modern animate-enter p-6" aria-live="polite">
			<div class="flex items-center justify-between">
				<span class={`rounded-full px-4 py-1.5 text-sm font-bold ${statusColor}`}
					>{statusLabel}</span
				>
				{#if today.ada_antrean}
					<span class="rounded-full bg-amber-100 px-3 py-1.5 text-xs font-bold text-amber-700">
						Menunggu sinkron
					</span>
				{/if}
			</div>

			{#if today.status === 'belum_absen'}
				<p class="mt-6 text-center text-6xl font-extrabold tabular-nums text-slate-900">
					{now
						.toLocaleTimeString('id-ID', {
							timeZone: 'Asia/Jakarta',
							hour: '2-digit',
							minute: '2-digit'
						})
						.replace('.', ':')}
				</p>
				<p class="mt-2 text-center text-slate-500">Ketuk tombol di bawah untuk mulai bekerja</p>
				<button
					type="button"
					on:click={() => keAbsen('MASUK')}
					class="pressable animate-enter-1 mt-6 flex min-h-[72px] w-full items-center justify-center gap-3 rounded-2xl hero-gradient-emerald text-2xl font-bold text-white shadow-lg shadow-emerald-600/30"
				>
					<Play size={28} /> Mulai Kerja
				</button>
			{:else if today.status === 'sedang_bekerja'}
				<p class="mt-6 text-center text-6xl font-extrabold tabular-nums text-slate-900">
					{formatStopwatch(tick)}
				</p>
				<p class="mt-2 flex items-center justify-center gap-1.5 text-slate-500">
					<Clock size={16} /> Mulai: {today.masuk_at ? formatJam(today.masuk_at) : '–'}
				</p>
				{#if today.lokasi_masuk}
					<p class="mt-1 flex items-center justify-center gap-1.5 text-sm text-slate-400">
						<MapPin size={14} /> <span class="truncate">{today.lokasi_masuk}</span>
					</p>
				{/if}
				<button
					type="button"
					on:click={() => keAbsen('KELUAR')}
					class="pressable animate-enter-1 mt-6 flex min-h-[72px] w-full items-center justify-center gap-3 rounded-2xl hero-gradient-red text-2xl font-bold text-white shadow-lg shadow-red-600/30"
				>
					<Square size={26} /> Selesai Kerja
				</button>
			{:else}
				<div class="mt-6 space-y-3 text-center">
					<div class="flex justify-between rounded-2xl bg-slate-50 px-5 py-4">
						<span class="font-medium text-slate-500">Masuk</span>
						<span class="text-lg font-bold tabular-nums text-slate-900">
							{today.masuk_at ? formatJam(today.masuk_at) : '–'}
						</span>
					</div>
					<div class="flex justify-between rounded-2xl bg-slate-50 px-5 py-4">
						<span class="font-medium text-slate-500">Keluar</span>
						<span class="text-lg font-bold tabular-nums text-slate-900">
							{today.keluar_at ? formatJam(today.keluar_at) : '–'}
						</span>
					</div>
					<div class="flex justify-between rounded-2xl bg-emerald-50 px-5 py-4">
						<span class="font-medium text-emerald-700">Total kerja</span>
						<span class="text-lg font-bold text-emerald-700">
							{today.total_menit !== null && today.total_menit !== undefined
								? formatDurasi(today.total_menit)
								: '–'}
						</span>
					</div>
				</div>
				<p class="mt-4 text-center text-sm text-slate-400">
					Kerja yang baik hari ini! Sampai jumpa besok. 👋
				</p>
			{/if}
		</section>

		<!-- Pintasan -->
		<section class="mt-5 grid grid-cols-3 gap-3" aria-label="Pintasan">
			<a
				href="/riwayat"
				class="pressable flex min-h-[96px] flex-col items-center justify-center gap-2 rounded-2xl bg-white p-4 shadow-sm"
			>
				<History size={28} class="text-emerald-600" />
				<span class="text-sm font-bold text-slate-700">Riwayat</span>
			</a>
			<a
				href="/slip-gaji"
				class="pressable flex min-h-[96px] flex-col items-center justify-center gap-2 rounded-2xl bg-white p-4 shadow-sm"
			>
				<Receipt size={28} class="text-sky-600" />
				<span class="text-sm font-bold text-slate-700">Slip Gaji</span>
			</a>
			{#if reimburseOn}
				<a
					href="/reimburse"
					class="pressable flex min-h-[96px] flex-col items-center justify-center gap-2 rounded-2xl bg-white p-4 shadow-sm"
				>
					<Wallet size={28} class="text-amber-600" />
					<span class="text-sm font-bold text-slate-700">Reimburse</span>
				</a>
			{/if}
		</section>
	{/if}
</div>
