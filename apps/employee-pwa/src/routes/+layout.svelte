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
	// Gerbang auth: jangan render halaman sebelum status sesi dipastikan,
	// agar tidak ada kedip (halaman terproteksi tampil sesaat lalu redirect).
	let authChecked = false;

	function guardRoute(path: string, loggedIn: boolean) {
		if (!loggedIn && !PUBLIC_ROUTES.includes(path)) {
			void goto('/login');
		} else if (loggedIn && path === '/login') {
			void goto('/');
		}
	}

	onMount(() => {
		void loadPublicSettings();

		// Penjaga rute awal: jalan SEKALI sebelum konten dirender.
		guardRoute(window.location.pathname, $isLoggedIn);
		authChecked = true;

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

	// Penjaga rute untuk navigasi berikutnya (setelah cek awal selesai).
	$: if (authChecked && typeof window !== 'undefined') {
		guardRoute($page.url.pathname, $isLoggedIn);
	}

	$: showNav = $isLoggedIn && !['/login', '/install', '/absen'].includes($page.url.pathname);
</script>

<div class="min-h-dvh bg-slate-50 text-slate-900">
	<OfflineBanner />
	<main class="mx-auto max-w-md pb-24">
		{#if authChecked}
			<slot />
		{:else}
			<!-- Splash anti-kedip saat status sesi dipastikan. -->
			<div class="flex min-h-[60dvh] flex-col items-center justify-center gap-4">
				<div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-600">
					<svg
						class="h-8 w-8 animate-pulse text-white"
						viewBox="0 0 24 24"
						fill="none"
						stroke="currentColor"
						stroke-width="2"
					>
						<circle cx="12" cy="12" r="9" />
						<path d="M12 7v5l3 2" stroke-linecap="round" />
					</svg>
				</div>
			</div>
		{/if}
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
