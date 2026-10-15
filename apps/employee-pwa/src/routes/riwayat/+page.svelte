<script lang="ts">
	import { onMount } from 'svelte';
	import { ChevronLeft, ChevronRight } from 'lucide-svelte';
	import { api, ApiError, type AttendanceHistoryItem } from '$lib/api';
	import { formatJam, formatDurasi, namaBulan, formatTanggalSingkat } from '$lib/format';
	import StatusBadge from '$lib/components/StatusBadge.svelte';
	import Skeleton from '$lib/components/Skeleton.svelte';
	import ErrorCard from '$lib/components/ErrorCard.svelte';
	import EmptyState from '$lib/components/EmptyState.svelte';

	let items: AttendanceHistoryItem[] = [];
	let loading = true;
	let error = '';

	// Bulan dalam WIB
	const nowWib = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Jakarta' }));
	let tahun = nowWib.getFullYear();
	let bulanIdx = nowWib.getMonth();

	$: bulanParam = `${tahun}-${String(bulanIdx + 1).padStart(2, '0')}`;
	$: labelBulan = `${namaBulan(bulanIdx)} ${tahun}`;

	function geser(delta: number) {
		const d = new Date(tahun, bulanIdx + delta, 1);
		tahun = d.getFullYear();
		bulanIdx = d.getMonth();
		void muat();
	}

	async function muat() {
		loading = true;
		error = '';
		try {
			const r = await api.history(bulanParam);
			items = r.items;
		} catch (e) {
			error = e instanceof ApiError ? e.message : 'Gagal memuat riwayat.';
		} finally {
			loading = false;
		}
	}

	onMount(muat);

	const statusMeta: Record<
		string,
		{ label: string; variant: 'success' | 'warning' | 'danger' | 'info' | 'neutral' }
	> = {
		hadir: { label: 'Hadir', variant: 'success' },
		terlambat: { label: 'Terlambat', variant: 'warning' },
		pulang_cepat: { label: 'Pulang Cepat', variant: 'warning' },
		lembur: { label: 'Lembur', variant: 'info' },
		tidak_lengkap: { label: 'Tidak Lengkap', variant: 'danger' },
		libur: { label: 'Libur', variant: 'neutral' }
	};
</script>

<svelte:head>
	<title>Riwayat Absensi — Absensi Kerja</title>
</svelte:head>

<div class="px-5 pb-6 pt-5">
	<h1 class="text-2xl font-extrabold">Riwayat Absensi</h1>

	<div class="mt-4 flex items-center justify-between rounded-2xl bg-white p-2 shadow-sm">
		<button
			type="button"
			on:click={() => geser(-1)}
			class="pressable flex h-12 w-12 items-center justify-center rounded-xl"
			aria-label="Bulan sebelumnya"
		>
			<ChevronLeft size={22} class="text-slate-600" />
		</button>
		<span class="text-lg font-bold">{labelBulan}</span>
		<button
			type="button"
			on:click={() => geser(1)}
			class="pressable flex h-12 w-12 items-center justify-center rounded-xl"
			aria-label="Bulan berikutnya"
		>
			<ChevronRight size={22} class="text-slate-600" />
		</button>
	</div>

	<div class="mt-4">
		{#if loading}
			<Skeleton lines={4} />
		{:else if error}
			<ErrorCard message={error} onRetry={muat} />
		{:else if items.length === 0}
			<EmptyState
				title="Belum ada riwayat"
				message={`Tidak ada catatan absen pada ${labelBulan}.`}
			/>
		{:else}
			<ul class="space-y-3">
				{#each items as it (it.id)}
					{@const meta = statusMeta[it.status] ?? { label: it.status, variant: 'neutral' }}
					<li class="rounded-2xl bg-white p-4 shadow-sm">
						<div class="flex items-center justify-between">
							<span class="font-bold">{formatTanggalSingkat(it.tanggal)}</span>
							<StatusBadge label={meta.label} variant={meta.variant} />
						</div>
						<div class="mt-3 grid grid-cols-3 gap-2 text-center">
							<div class="rounded-xl bg-slate-50 py-2">
								<p class="text-[11px] font-medium text-slate-400">Masuk</p>
								<p class="font-bold tabular-nums">{it.masuk_at ? formatJam(it.masuk_at) : '–'}</p>
							</div>
							<div class="rounded-xl bg-slate-50 py-2">
								<p class="text-[11px] font-medium text-slate-400">Keluar</p>
								<p class="font-bold tabular-nums">{it.keluar_at ? formatJam(it.keluar_at) : '–'}</p>
							</div>
							<div class="rounded-xl bg-emerald-50 py-2">
								<p class="text-[11px] font-medium text-emerald-600">Total</p>
								<p class="font-bold tabular-nums text-emerald-700">
									{it.total_menit !== null ? formatDurasi(it.total_menit) : '–'}
								</p>
							</div>
						</div>
					</li>
				{/each}
			</ul>
		{/if}
	</div>
</div>
