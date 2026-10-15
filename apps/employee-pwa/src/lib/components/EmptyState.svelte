<script lang="ts">
	import { Inbox } from 'lucide-svelte';
	export let title = 'Belum ada data';
	export let message = '';
</script>

<div class="rounded-2xl bg-white p-8 text-center shadow-sm">
	<div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-slate-100">
		<Inbox size={24} class="text-slate-400" />
	</div>
	<p class="font-bold text-slate-900">{title}</p>
	{#if message}
		<p class="mt-1 text-sm text-slate-500">{message}</p>
	{/if}
</div>
