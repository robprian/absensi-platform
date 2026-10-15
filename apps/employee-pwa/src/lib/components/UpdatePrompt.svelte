<script lang="ts">
	import { RefreshCw, X } from 'lucide-svelte';

	/** Dipicu oleh registerSW dari vite-plugin-pwa saat update tersedia. */
	export let onUpdate: (() => void) | undefined = undefined;
	export let onDismiss: (() => void) | undefined = undefined;
	export let visible = false;
</script>

{#if visible}
	<div class="fixed inset-x-0 bottom-20 z-50 mx-auto max-w-md px-4">
		<div class="flex items-center gap-3 rounded-2xl bg-slate-900 p-4 text-white shadow-lg">
			<RefreshCw size={22} class="shrink-0 text-emerald-400" />
			<div class="min-w-0 flex-1">
				<p class="text-sm font-bold">Versi baru tersedia</p>
				<p class="text-xs text-slate-300">Perbarui aplikasi untuk fitur terbaru.</p>
			</div>
			<button
				type="button"
				on:click={onUpdate}
				class="pressable min-h-[44px] rounded-xl bg-emerald-600 px-4 text-sm font-bold"
			>
				Perbarui
			</button>
			<button
				type="button"
				on:click={onDismiss}
				class="pressable flex h-11 w-11 items-center justify-center rounded-xl"
				aria-label="Tutup"
			>
				<X size={18} />
			</button>
		</div>
	</div>
{/if}
