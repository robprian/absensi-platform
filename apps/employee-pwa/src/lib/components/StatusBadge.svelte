<script lang="ts">
	export let variant: 'info' | 'success' | 'warning' | 'danger' | 'neutral' = 'neutral';
	export let label = '';

	const styles: Record<string, string> = {
		info: 'bg-sky-100 text-sky-700',
		success: 'bg-emerald-100 text-emerald-700',
		warning: 'bg-amber-100 text-amber-700',
		danger: 'bg-red-100 text-red-700',
		neutral: 'bg-slate-100 text-slate-600'
	};
</script>

<span
	class={`inline-flex items-center rounded-full px-3 py-1 text-xs font-bold ${styles[variant]}`}
>
	{label}
</span>
