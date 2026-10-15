<script lang="ts">
	import { onMount } from 'svelte';
	import { WifiOff, RefreshCw } from 'lucide-svelte';
	import { offlineQueue } from '$lib/offline';

	let online = true;
	let pending = 0;

	onMount(() => {
		online = navigator.onLine;
		const update = () => {
			online = navigator.onLine;
			if (online) void refreshPending();
		};
		const refreshPending = () =>
			offlineQueue
				.count()
				.then((c) => (pending = c))
				.catch(() => {});
		refreshPending();
		window.addEventListener('online', update);
		window.addEventListener('offline', update);
		const t = setInterval(refreshPending, 10_000);
		return () => {
			window.removeEventListener('online', update);
			window.removeEventListener('offline', update);
			clearInterval(t);
		};
	});
</script>

{#if !online}
	<div
		class="safe-top fixed inset-x-0 top-0 z-50 bg-amber-500 px-4 py-2 text-center text-sm font-semibold text-white"
		role="alert"
	>
		<span class="inline-flex items-center gap-2">
			<WifiOff size={16} /> Anda sedang offline. Absen akan disimpan dan dikirim otomatis.
		</span>
	</div>
{:else if pending > 0}
	<div
		class="safe-top fixed inset-x-0 top-0 z-50 bg-sky-600 px-4 py-2 text-center text-sm font-semibold text-white"
		role="status"
	>
		<span class="inline-flex items-center gap-2">
			<RefreshCw size={16} class="animate-spin" />
			{pending} absen menunggu sinkronisasi…
		</span>
	</div>
{/if}
