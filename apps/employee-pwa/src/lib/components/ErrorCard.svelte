<script lang="ts">
	import { TriangleAlert, RefreshCw } from 'lucide-svelte';
	export let title = 'Terjadi kesalahan';
	export let message = 'Coba lagi dalam beberapa saat.';
	export let onRetry: (() => void) | undefined = undefined;
</script>

<div class="rounded-2xl bg-white p-6 text-center shadow-sm">
	<div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-red-50">
		<TriangleAlert size={24} class="text-red-600" />
	</div>
	<p class="font-bold text-slate-900">{title}</p>
	<p class="mt-1 text-sm text-slate-500">{message}</p>
	{#if onRetry}
		<button
			type="button"
			on:click={onRetry}
			class="pressable mt-4 inline-flex min-h-[48px] items-center gap-2 rounded-2xl bg-slate-900 px-6 font-semibold text-white"
		>
			<RefreshCw size={18} /> Coba Lagi
		</button>
	{/if}
</div>
