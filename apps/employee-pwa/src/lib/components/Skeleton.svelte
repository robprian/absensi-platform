<script lang="ts">
	/** Skeleton loading generik. */
	export let lines = 3;
	export let className = '';
</script>

<div class={className} aria-busy="true" aria-label="Memuat…">
	{#each Array.from({ length: lines }, (_, i) => i) as i (i)}
		<div class="skeleton mb-3 h-16 w-full"></div>
	{/each}
</div>
