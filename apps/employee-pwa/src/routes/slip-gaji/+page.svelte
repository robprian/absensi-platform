<script lang="ts">
	import { onMount } from 'svelte';
	import { Receipt, ChevronRight } from 'lucide-svelte';
	import { api, ApiError, type PayslipSummary } from '$lib/api';
	import { formatRupiah } from '$lib/format';
	import StatusBadge from '$lib/components/StatusBadge.svelte';
	import Skeleton from '$lib/components/Skeleton.svelte';
	import ErrorCard from '$lib/components/ErrorCard.svelte';
	import EmptyState from '$lib/components/EmptyState.svelte';

	let items: PayslipSummary[] = [];
	let loading = true;
	let error = '';

	async function muat() {
		loading = true;
		error = '';
		try {
			const r = await api.payslips();
			items = r.items;
		} catch (e) {
			error = e instanceof ApiError ? e.message : 'Gagal memuat slip gaji.';
		} finally {
			loading = false;
		}
	}

	onMount(muat);
</script>

<svelte:head>
	<title>Slip Gaji — Absensi Kerja</title>
</svelte:head>

<div class="px-5 pb-6 pt-5">
	<h1 class="text-2xl font-extrabold">Slip Gaji</h1>
	<p class="mt-1 text-sm text-slate-500">Hanya slip yang sudah final yang ditampilkan.</p>

	<div class="mt-4">
		{#if loading}
			<Skeleton lines={3} />
		{:else if error}
			<ErrorCard message={error} onRetry={muat} />
		{:else if items.length === 0}
			<EmptyState
				title="Belum ada slip gaji"
				message="Slip gaji final akan muncul di sini setelah diterbitkan."
			/>
		{:else}
			<ul class="space-y-3">
				{#each items as s (s.id)}
					<li>
						<a
							href={`/slip-gaji/${s.id}`}
							class="pressable flex items-center gap-4 rounded-2xl bg-white p-5 shadow-sm"
						>
							<div
								class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-sky-100"
							>
								<Receipt size={24} class="text-sky-600" />
							</div>
							<div class="min-w-0 flex-1">
								<p class="font-bold">{s.periode}</p>
								<p class="text-lg font-extrabold text-emerald-700">
									{formatRupiah(s.total_diterima)}
								</p>
							</div>
							<StatusBadge
								label={s.status_bayar === 'dibayar' ? 'Dibayar' : 'Belum Dibayar'}
								variant={s.status_bayar === 'dibayar' ? 'success' : 'warning'}
							/>
							<ChevronRight size={20} class="shrink-0 text-slate-300" />
						</a>
					</li>
				{/each}
			</ul>
		{/if}
	</div>
</div>
