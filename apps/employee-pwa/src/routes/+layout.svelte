<script lang="ts">
	import '../app.css';
	import { onMount } from 'svelte';
	import { page } from '$app/stores';
	import { goto } from '$app/navigation';
	import { registerSW } from 'virtual:pwa-register';
	import { isLoggedIn } from '$lib/auth';
	import { loadPublicSettings } from '$lib/settings';
	import { syncPendingAttendance } from '$lib/sync';
	import OfflineBanner from '$lib/components/OfflineBanner.svelte';
	import UpdatePrompt from '$lib/components/UpdatePrompt.svelte';
	import BottomNav from '$lib/components/BottomNav.svelte';

	const PUBLIC_ROUTES = ['/login', '/install'];

	let updateAvailable = false;
	let updateSW: ((reload: boolean) => Promise<void>) | undefined;

	onMount(() => {
		void loadPublicSettings();

		updateSW = registerSW({
			immediate: true,
			onNeedRefresh() {
				updateAvailable = true;
			},
			onOfflineReady() {}
		});

		// Sinkronkan antrean offline setiap kali kembali online.
		const onOnline = () => void syncPendingAttendance();
		window.addEventListener('online', onOnline);
		void syncPendingAttendance();
		return () => window.removeEventListener('online', onOnline);
	});

	// Penjaga rute: semua kecuali login/install butuh sesi.
	$: if (typeof window !== 'undefined') {
		const path = $page.url.pathname;
		if (!$isLoggedIn && !PUBLIC_ROUTES.includes(path)) {
			void goto('/login');
		} else if ($isLoggedIn && path === '/login') {
			void goto('/');
		}
	}

	$: showNav = $isLoggedIn && !['/login', '/install', '/absen'].includes($page.url.pathname);
</script>

<div class="min-h-dvh bg-slate-50 text-slate-900">
	<OfflineBanner />
	<main class="mx-auto max-w-md pb-24">
		<slot />
	</main>
	{#if showNav}
		<BottomNav />
	{/if}
	<UpdatePrompt
		visible={updateAvailable}
		onUpdate={() => updateSW?.(true)}
		onDismiss={() => (updateAvailable = false)}
	/>
</div>
