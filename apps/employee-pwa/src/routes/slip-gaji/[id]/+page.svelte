<script lang="ts">
	import { onMount } from 'svelte';
	import { page } from '$app/stores';
	import { goto } from '$app/navigation';
	import { Download, ChevronLeft, FileText, Loader2 } from 'lucide-svelte';
	import { api, ApiError, type PayslipDetail } from '$lib/api';
	import { formatRupiah } from '$lib/format';
	import { employee } from '$lib/auth';
	import StatusBadge from '$lib/components/StatusBadge.svelte';
	import Skeleton from '$lib/components/Skeleton.svelte';
	import ErrorCard from '$lib/components/ErrorCard.svelte';

	const id = $page.params.id ?? '';
	if (!id) void goto('/slip-gaji');

	let slip: PayslipDetail | null = null;
	let loading = true;
	let error = '';
	let downloading = false;

	async function muat() {
		loading = true;
		error = '';
		try {
			slip = await api.payslipDetail(id);
		} catch (e) {
			error = e instanceof ApiError ? e.message : 'Gagal memuat slip gaji.';
		} finally {
			loading = false;
		}
	}

	async function unduh() {
		if (!slip || downloading) return;
		downloading = true;
		try {
			const { url } = await api.payslipDownloadUrl(slip.id);
			// URL bertanda tangan & berumur pendek dari backend — buka langsung.
			window.open(url, '_blank', 'noopener');
		} catch (e) {
			error = e instanceof ApiError ? e.message : 'Gagal mengunduh PDF.';
		} finally {
			downloading = false;
		}
	}

	onMount(muat);
</script>

<svelte:head>
	<title>Detail Slip Gaji — Absensi Kerja</title>
</svelte:head>

<div class="px-5 pb-6 pt-5">
	<div class="flex items-center gap-2">
		<button
			type="button"
			on:click={() => goto('/slip-gaji')}
			class="pressable flex h-11 w-11 items-center justify-center rounded-xl bg-white shadow-sm"
			aria-label="Kembali"
		>
			<ChevronLeft size={22} class="text-slate-600" />
		</button>
		<h1 class="text-2xl font-extrabold">Slip Gaji</h1>
	</div>

	<div class="mt-4">
		{#if loading}
			<Skeleton lines={5} />
		{:else if error && !slip}
			<ErrorCard message={error} onRetry={muat} />
		{:else if slip}
			<section class="rounded-3xl bg-white p-6 shadow-sm">
				<div class="flex items-start justify-between">
					<div>
						<p class="text-sm text-slate-500">Periode</p>
						<p class="text-xl font-extrabold">{slip.periode}</p>
						<p class="mt-1 text-xs text-slate-400">No. {slip.nomor_dokumen}</p>
					</div>
					<StatusBadge
						label={slip.status_bayar === 'dibayar' ? 'Dibayar' : 'Belum Dibayar'}
						variant={slip.status_bayar === 'dibayar' ? 'success' : 'warning'}
					/>
				</div>

				<div class="mt-4 rounded-2xl bg-slate-50 p-4 text-sm">
					<p class="font-bold">{$employee?.nama ?? ''}</p>
					<p class="text-slate-500">{$employee?.employee_id ?? ''}</p>
				</div>

				<dl class="mt-5 space-y-3 text-[15px]">
					<div class="flex justify-between">
						<dt class="text-slate-500">Gaji Pokok</dt>
						<dd class="font-semibold tabular-nums">{formatRupiah(slip.gaji_pokok)}</dd>
					</div>
					<div class="flex justify-between">
						<dt class="text-slate-500">Insentif Kehadiran</dt>
						<dd class="font-semibold tabular-nums">{formatRupiah(slip.insentif_kehadiran)}</dd>
					</div>
					<div class="flex justify-between">
						<dt class="text-slate-500">Lembur</dt>
						<dd class="font-semibold tabular-nums">{formatRupiah(slip.lembur)}</dd>
					</div>
					<div class="flex justify-between">
						<dt class="text-slate-500">Tunjangan</dt>
						<dd class="font-semibold tabular-nums">{formatRupiah(slip.tunjangan)}</dd>
					</div>
					{#if slip.reimburse_disetujui > 0}
						<div class="flex justify-between">
							<dt class="text-slate-500">Reimburse Disetujui</dt>
							<dd class="font-semibold tabular-nums">{formatRupiah(slip.reimburse_disetujui)}</dd>
						</div>
					{/if}
					<div class="flex justify-between">
						<dt class="text-slate-500">Potongan</dt>
						<dd class="font-semibold tabular-nums text-red-600">
							− {formatRupiah(slip.potongan)}
						</dd>
					</div>
					{#each slip.rincian as r, i (i)}
						<div class="flex justify-between">
							<dt class="text-slate-500">{r.label}</dt>
							<dd class={`font-semibold tabular-nums ${r.tipe === 'kurang' ? 'text-red-600' : ''}`}>
								{r.tipe === 'kurang' ? '− ' : ''}{formatRupiah(r.nominal)}
							</dd>
						</div>
					{/each}
				</dl>

				<div class="mt-5 flex items-center justify-between rounded-2xl bg-emerald-50 px-5 py-4">
					<span class="font-bold text-emerald-800">Total Diterima</span>
					<span class="text-2xl font-extrabold tabular-nums text-emerald-700">
						{formatRupiah(slip.total_diterima)}
					</span>
				</div>

				<button
					type="button"
					on:click={unduh}
					disabled={downloading}
					class="pressable mt-5 flex min-h-[60px] w-full items-center justify-center gap-2 rounded-2xl bg-slate-900 text-lg font-bold text-white disabled:opacity-60"
				>
					{#if downloading}
						<Loader2 size={22} class="animate-spin" /> Menyiapkan…
					{:else}
						<Download size={22} /> Unduh PDF
					{/if}
				</button>
				<p class="mt-3 flex items-center justify-center gap-1.5 text-xs text-slate-400">
					<FileText size={14} /> Dokumen resmi perusahaan — simpan dengan baik.
				</p>
			</section>
		{/if}
	</div>
</div>
