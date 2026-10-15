<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { Plus, X, Camera, TriangleAlert, Loader2 } from 'lucide-svelte';
	import { api, ApiError, type Reimbursement, type ReimburseStatus } from '$lib/api';
	import { publicSettings } from '$lib/settings';
	import { formatRupiah, formatTanggalSingkat } from '$lib/format';
	import StatusBadge from '$lib/components/StatusBadge.svelte';
	import Skeleton from '$lib/components/Skeleton.svelte';
	import ErrorCard from '$lib/components/ErrorCard.svelte';
	import EmptyState from '$lib/components/EmptyState.svelte';

	const KATEGORI = ['Transportasi', 'Makan', 'Parkir', 'Pulsa / Internet', 'ATK', 'Lainnya'];

	let items: Reimbursement[] = [];
	let loading = true;
	let error = '';

	let showForm = false;
	let kategori = KATEGORI[0];
	let nominalText = '';
	let tanggal = new Date().toISOString().slice(0, 10);
	let keterangan = '';
	let buktiFile: File | null = null;
	let buktiPreview: string | null = null;
	let submitting = false;
	let formError = '';

	const statusMeta: Record<
		ReimburseStatus,
		{ label: string; variant: 'warning' | 'success' | 'danger' | 'info' }
	> = {
		menunggu: { label: 'Menunggu', variant: 'warning' },
		disetujui: { label: 'Disetujui', variant: 'info' },
		ditolak: { label: 'Ditolak', variant: 'danger' },
		dibayar: { label: 'Dibayar', variant: 'success' }
	};

	async function muat() {
		loading = true;
		error = '';
		try {
			const r = await api.reimbursements();
			items = r.items;
		} catch (e) {
			error = e instanceof ApiError ? e.message : 'Gagal memuat data reimburse.';
		} finally {
			loading = false;
		}
	}

	onMount(() => {
		// Fitur dimatikan → jangan tampilkan apa pun di sini.
		if ($publicSettings && !$publicSettings.reimbursement_enabled) {
			void goto('/');
			return;
		}
		void muat();
	});

	/** Parse "35.000" / "35000" → 35000 (integer). */
	function parseNominal(text: string): number | null {
		const digits = text.replace(/[^\d]/g, '');
		if (!digits) return null;
		const n = Number(digits);
		return Number.isSafeInteger(n) && n > 0 ? n : null;
	}

	function onFileChange(e: Event) {
		const input = e.target as HTMLInputElement;
		const f = input.files?.[0] ?? null;
		if (!f) return;
		if (f.size > 5 * 1024 * 1024) {
			formError = 'Ukuran bukti maksimal 5 MB.';
			return;
		}
		buktiFile = f;
		if (buktiPreview) URL.revokeObjectURL(buktiPreview);
		buktiPreview = f.type.startsWith('image/') ? URL.createObjectURL(f) : null;
	}

	async function ajukan() {
		formError = '';
		const nominal = parseNominal(nominalText);
		if (!nominal) {
			formError = 'Masukkan nominal yang valid (contoh: 35000).';
			return;
		}
		if (!tanggal) {
			formError = 'Pilih tanggal pengeluaran.';
			return;
		}
		if (keterangan.trim().length < 3) {
			formError = 'Tulis keterangan singkat (min. 3 huruf).';
			return;
		}
		if (!buktiFile) {
			formError = 'Lampirkan foto / PDF bukti pengeluaran.';
			return;
		}
		submitting = true;
		try {
			const form = new FormData();
			form.append('kategori', kategori);
			form.append('nominal', String(nominal));
			form.append('tanggal', tanggal);
			form.append('keterangan', keterangan.trim());
			form.append('bukti', buktiFile);
			await api.submitReimbursement(form);
			showForm = false;
			resetForm();
			await muat();
		} catch (e) {
			formError = e instanceof ApiError ? e.message : 'Gagal mengajukan. Coba lagi.';
		} finally {
			submitting = false;
		}
	}

	function resetForm() {
		kategori = KATEGORI[0];
		nominalText = '';
		tanggal = new Date().toISOString().slice(0, 10);
		keterangan = '';
		buktiFile = null;
		if (buktiPreview) URL.revokeObjectURL(buktiPreview);
		buktiPreview = null;
		formError = '';
	}
</script>

<svelte:head>
	<title>Reimburse — Absensi Kerja</title>
</svelte:head>

<div class="px-5 pb-6 pt-5">
	<div class="flex items-center justify-between">
		<h1 class="text-2xl font-extrabold">Reimburse</h1>
		<button
			type="button"
			on:click={() => (showForm = true)}
			class="pressable flex min-h-[48px] items-center gap-2 rounded-2xl bg-emerald-600 px-5 font-bold text-white shadow-sm"
		>
			<Plus size={20} /> Ajukan
		</button>
	</div>

	<div class="mt-4">
		{#if loading}
			<Skeleton lines={3} />
		{:else if error}
			<ErrorCard message={error} onRetry={muat} />
		{:else if items.length === 0}
			<EmptyState
				title="Belum ada pengajuan"
				message="Ketuk “Ajukan” untuk mengajukan penggantian biaya."
			/>
		{:else}
			<ul class="space-y-3">
				{#each items as r (r.id)}
					{@const meta = statusMeta[r.status]}
					<li class="rounded-2xl bg-white p-4 shadow-sm">
						<div class="flex items-center justify-between gap-2">
							<span class="font-bold">{r.kategori}</span>
							<StatusBadge label={meta.label} variant={meta.variant} />
						</div>
						<p class="mt-1 text-lg font-extrabold text-slate-900">{formatRupiah(r.nominal)}</p>
						<p class="mt-1 text-sm text-slate-500">
							{formatTanggalSingkat(r.tanggal)} • {r.keterangan}
						</p>
					</li>
				{/each}
			</ul>
		{/if}
	</div>
</div>

{#if showForm}
	<div
		class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center"
		role="dialog"
		aria-modal="true"
		aria-label="Form pengajuan reimburse"
	>
		<div
			class="max-h-[92dvh] w-full max-w-md overflow-y-auto rounded-t-3xl bg-white p-6 sm:rounded-3xl"
		>
			<div class="mb-5 flex items-center justify-between">
				<h2 class="text-xl font-extrabold">Ajukan Reimburse</h2>
				<button
					type="button"
					on:click={() => (showForm = false)}
					class="pressable flex h-11 w-11 items-center justify-center rounded-xl bg-slate-100"
					aria-label="Tutup"
				>
					<X size={20} class="text-slate-600" />
				</button>
			</div>

			<div class="space-y-4">
				<div>
					<label for="rb-kategori" class="mb-2 block text-sm font-bold text-slate-700"
						>Kategori</label
					>
					<select
						id="rb-kategori"
						bind:value={kategori}
						class="min-h-[56px] w-full rounded-2xl border-2 border-slate-200 bg-slate-50 px-4 text-lg outline-none focus:border-emerald-600"
					>
						{#each KATEGORI as k (k)}<option value={k}>{k}</option>{/each}
					</select>
				</div>

				<div>
					<label for="rb-nominal" class="mb-2 block text-sm font-bold text-slate-700"
						>Nominal (Rp)</label
					>
					<input
						id="rb-nominal"
						type="text"
						inputmode="numeric"
						bind:value={nominalText}
						placeholder="cth: 35000"
						class="min-h-[56px] w-full rounded-2xl border-2 border-slate-200 bg-slate-50 px-4 text-lg tabular-nums outline-none focus:border-emerald-600"
					/>
				</div>

				<div>
					<label for="rb-tanggal" class="mb-2 block text-sm font-bold text-slate-700">Tanggal</label
					>
					<input
						id="rb-tanggal"
						type="date"
						bind:value={tanggal}
						class="min-h-[56px] w-full rounded-2xl border-2 border-slate-200 bg-slate-50 px-4 text-lg outline-none focus:border-emerald-600"
					/>
				</div>

				<div>
					<label for="rb-ket" class="mb-2 block text-sm font-bold text-slate-700">Keterangan</label>
					<textarea
						id="rb-ket"
						bind:value={keterangan}
						rows={3}
						placeholder="cth: Ongkos menuju lokasi kerja"
						class="w-full rounded-2xl border-2 border-slate-200 bg-slate-50 p-4 text-base outline-none focus:border-emerald-600"
					></textarea>
				</div>

				<div>
					<span class="mb-2 block text-sm font-bold text-slate-700">Bukti (foto / PDF)</span>
					<label
						class="pressable flex min-h-[120px] cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 p-4"
					>
						{#if buktiPreview}
							<img src={buktiPreview} alt="Pratinjau bukti" class="max-h-40 rounded-xl" />
						{:else if buktiFile}
							<p class="text-sm font-semibold text-slate-600">{buktiFile.name}</p>
						{:else}
							<Camera size={28} class="text-slate-400" />
							<span class="text-sm text-slate-500">Ketuk untuk memilih foto / PDF</span>
						{/if}
						<input type="file" accept="image/*,.pdf" class="hidden" on:change={onFileChange} />
					</label>
				</div>

				{#if formError}
					<div
						class="flex items-start gap-2 rounded-2xl bg-red-50 p-4 text-sm font-medium text-red-700"
						role="alert"
					>
						<TriangleAlert size={18} class="mt-0.5 shrink-0" />
						<span>{formError}</span>
					</div>
				{/if}

				<button
					type="button"
					on:click={ajukan}
					disabled={submitting}
					class="pressable flex min-h-[60px] w-full items-center justify-center gap-2 rounded-2xl bg-emerald-600 text-lg font-bold text-white shadow-lg disabled:opacity-60"
				>
					{#if submitting}
						<Loader2 size={22} class="animate-spin" /> Mengirim…
					{:else}
						Ajukan Reimburse
					{/if}
				</button>
			</div>
		</div>
	</div>
{/if}
