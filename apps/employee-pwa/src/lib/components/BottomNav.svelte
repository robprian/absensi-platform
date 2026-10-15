<script lang="ts">
	import { page } from '$app/stores';
	import { Home, History, Receipt, User } from 'lucide-svelte';

	const items = [
		{ href: '/', label: 'Beranda', icon: Home },
		{ href: '/riwayat', label: 'Riwayat', icon: History },
		{ href: '/slip-gaji', label: 'Slip Gaji', icon: Receipt },
		{ href: '/profil', label: 'Profil', icon: User }
	];

	$: current = $page.url.pathname;
</script>

<nav
	class="safe-bottom fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 backdrop-blur"
	aria-label="Navigasi utama"
>
	<div class="mx-auto grid max-w-md grid-cols-4">
		{#each items as item (item.href)}
			{@const active = item.href === '/' ? current === '/' : current.startsWith(item.href)}
			<a
				href={item.href}
				class="pressable flex min-h-[64px] flex-col items-center justify-center gap-1 px-2 py-2"
				aria-current={active ? 'page' : undefined}
			>
				<item.icon
					size={24}
					strokeWidth={active ? 2.5 : 2}
					class={active ? 'text-emerald-600' : 'text-slate-400'}
				/>
				<span class={`text-[11px] font-semibold ${active ? 'text-emerald-700' : 'text-slate-500'}`}>
					{item.label}
				</span>
				{#if active}
					<span class="h-1 w-8 rounded-full bg-emerald-600" aria-hidden="true"></span>
				{/if}
			</a>
		{/each}
	</div>
</nav>
