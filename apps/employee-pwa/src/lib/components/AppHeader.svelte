<script lang="ts">
	import { Bell, Clock3 } from 'lucide-svelte';
	import { hariIniWib } from '$lib/format';

	export let nama = '';
	export let unreadCount = 0;
	export let onBell: (() => void) | undefined = undefined;
</script>

<header class="safe-top bg-white">
	<div class="mx-auto flex max-w-md items-center gap-3 px-5 pb-4 pt-4">
		<div
			class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-600 shadow-sm"
		>
			<Clock3 size={24} class="text-white" />
		</div>
		<div class="min-w-0 flex-1">
			<p class="truncate text-lg font-bold text-slate-900">Halo, {nama || 'Karyawan'} 👋</p>
			<p class="text-sm text-slate-500">{hariIniWib()}</p>
		</div>
		<button
			type="button"
			on:click={onBell}
			class="pressable relative flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100"
			aria-label="Notifikasi"
		>
			<Bell size={22} class="text-slate-600" />
			{#if unreadCount > 0}
				<span
					class="absolute right-2 top-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[11px] font-bold text-white"
				>
					{unreadCount > 9 ? '9+' : unreadCount}
				</span>
			{/if}
		</button>
	</div>
</header>
