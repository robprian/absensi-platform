<script lang="ts">
	import { Bell } from 'lucide-svelte';
	import { hariIniWib } from '$lib/format';
	import { publicSettings } from '$lib/settings';

	export let nama = '';
	export let unreadCount = 0;
	export let onBell: (() => void) | undefined = undefined;

	$: brand = $publicSettings?.brand;
	$: logoUrl = brand?.logo_url ?? null;
</script>

<header class="safe-top glass sticky top-0 z-20">
	<div class="mx-auto flex max-w-md items-center gap-3 px-5 pb-3 pt-4">
		{#if logoUrl}
			<img src={logoUrl} alt="Logo" class="h-11 w-11 shrink-0 rounded-2xl object-contain bg-white shadow-sm p-1" />
		{:else}
			<div
				class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl hero-gradient-emerald shadow-md shadow-emerald-600/25"
			>
				<span class="text-xl font-extrabold text-white">A</span>
			</div>
		{/if}
		<div class="min-w-0 flex-1">
			<p class="truncate text-lg font-bold text-slate-900">Halo, {nama || 'Karyawan'} 👋</p>
			<p class="text-sm text-slate-500">{hariIniWib()}</p>
		</div>
		<button
			type="button"
			on:click={onBell}
			class="pressable relative flex h-11 w-11 items-center justify-center rounded-2xl bg-white shadow-sm border border-slate-100"
			aria-label="Notifikasi"
		>
			<Bell size={20} class="text-slate-600" />
			{#if unreadCount > 0}
				<span
					class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-[11px] font-bold text-white shadow-sm animate-soft-pulse"
				>
					{unreadCount > 9 ? '9+' : unreadCount}
				</span>
			{/if}
		</button>
	</div>
</header>
