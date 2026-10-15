<script lang="ts">
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { Bell, ChevronLeft, CheckCheck } from 'lucide-svelte';
	import { api, ApiError, type AppNotification } from '$lib/api';
	import { formatTanggalJamWib } from '$lib/format';
	import Skeleton from '$lib/components/Skeleton.svelte';
	import ErrorCard from '$lib/components/ErrorCard.svelte';
	import EmptyState from '$lib/components/EmptyState.svelte';

	let items: AppNotification[] = [];
	let loading = true;
	let error = '';

	async function muat() {
		loading = true;
		error = '';
		try {
			const r = await api.notifications();
			items = r.items;
		} catch (e) {
			error = e instanceof ApiError ? e.message : 'Gagal memuat notifikasi.';
		} finally {
			loading = false;
		}
	}

	async function tandaiSemua() {
		try {
			await Promise.all(
				items.filter((n) => !n.sudah_dibaca).map((n) => api.markNotificationRead(n.id))
			);
			items = items.map((n) => ({ ...n, sudah_dibaca: true }));
		} catch {
			// non-kritis
		}
	}

	onMount(muat);
</script>

<svelte:head>
	<title>Notifikasi — Absensi Kerja</title>
</svelte:head>

<div class="px-5 pb-6 pt-5">
	<div class="flex items-center justify-between">
		<div class="flex items-center gap-2">
			<button
				type="button"
				on:click={() => goto('/')}
				class="pressable flex h-11 w-11 items-center justify-center rounded-xl bg-white shadow-sm"
				aria-label="Kembali"
			>
				<ChevronLeft size={22} class="text-slate-600" />
			</button>
			<h1 class="text-2xl font-extrabold">Notifikasi</h1>
		</div>
		{#if items.some((n) => !n.sudah_dibaca)}
			<button
				type="button"
				on:click={tandaiSemua}
				class="pressable flex min-h-[44px] items-center gap-1.5 rounded-xl bg-slate-100 px-3 text-sm font-bold text-slate-600"
			>
				<CheckCheck size={18} /> Tandai dibaca
			</button>
		{/if}
	</div>

	<div class="mt-4">
		{#if loading}
			<Skeleton lines={3} />
		{:else if error}
			<ErrorCard message={error} onRetry={muat} />
		{:else if items.length === 0}
			<EmptyState title="Belum ada notifikasi" />
		{:else}
			<ul class="space-y-3">
				{#each items as n (n.id)}
					<li class={`rounded-2xl p-4 shadow-sm ${n.sudah_dibaca ? 'bg-white' : 'bg-emerald-50'}`}>
						<div class="flex items-start gap-3">
							<div
								class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100"
							>
								<Bell size={20} class="text-emerald-700" />
							</div>
							<div class="min-w-0">
								<p class="font-bold">{n.judul}</p>
								<p class="mt-0.5 text-sm text-slate-600">{n.isi}</p>
								<p class="mt-1 text-xs text-slate-400">{formatTanggalJamWib(n.dibuat_at)}</p>
							</div>
						</div>
					</li>
				{/each}
			</ul>
		{/if}
	</div>
</div>
