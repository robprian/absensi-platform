import { writable } from 'svelte/store';
import { api, type PublicSettings } from './api';

export const publicSettings = writable<PublicSettings | null>(null);
export const settingsLoading = writable(true);

let loaded = false;

/** Muat pengaturan publik sekali (mis. reimbursement_enabled). */
export async function loadPublicSettings(): Promise<void> {
	if (loaded) return;
	try {
		const s = await api.publicSettings();
		publicSettings.set(s);
		// Terapkan favicon & warna tema dinamis.
		applyBranding(s.brand);
	} catch {
		publicSettings.set({
			reimbursement_enabled: false,
			nama_perusahaan: 'Perusahaan',
			brand: { logo_url: null, icon_192_url: null, icon_512_url: null, favicon_url: null, primary_color: '#059669' }
		});
	} finally {
		settingsLoading.set(false);
		loaded = true;
	}
}

/** Terapkan branding dinamis: favicon + theme-color + manifest PWA. */
function applyBranding(brand: { favicon_url: string | null; primary_color: string; icon_192_url: string | null; icon_512_url: string | null }): void {
	if (typeof document === 'undefined') return;
	try {
		if (brand.favicon_url) {
			let link = document.querySelector<HTMLLinkElement>("link[rel='icon']");
			if (!link) {
				link = document.createElement('link');
				link.rel = 'icon';
				document.head.appendChild(link);
			}
			link.href = brand.favicon_url;
		}
		let meta = document.querySelector<HTMLMetaElement>("meta[name='theme-color']");
		if (!meta) {
			meta = document.createElement('meta');
			meta.name = 'theme-color';
			document.head.appendChild(meta);
		}
		meta.content = brand.primary_color;
		// CSS variable untuk tema dinamis.
		document.documentElement.style.setProperty('--brand-primary', brand.primary_color);

		// Manifest dinamis: pakai ikon custom jika ada.
		if (brand.icon_192_url || brand.icon_512_url) {
			const icons = [];
			if (brand.icon_192_url) icons.push({ src: brand.icon_192_url, sizes: '192x192', type: 'image/png', purpose: 'any' });
			if (brand.icon_512_url) icons.push({ src: brand.icon_512_url, sizes: '512x512', type: 'image/png', purpose: 'any' });
			// Fallback ke ikon default untuk maskable.
			icons.push({ src: 'icons/icon-maskable-192.png', sizes: '192x192', type: 'image/png', purpose: 'maskable' });
			icons.push({ src: 'icons/icon-maskable-512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' });

			const manifest = {
				name: 'AbsensiKu',
				short_name: 'AbsensiKu',
				start_url: '/',
				display: 'standalone',
				background_color: '#ffffff',
				theme_color: brand.primary_color,
				icons
			};
			const blob = new Blob([JSON.stringify(manifest)], { type: 'application/manifest+json' });
			const url = URL.createObjectURL(blob);
			let link = document.querySelector<HTMLLinkElement>("link[rel='manifest']");
			if (!link) {
				link = document.createElement('link');
				link.rel = 'manifest';
				document.head.appendChild(link);
			}
			link.href = url;
		}
	} catch {
		// abaikan; branding best-effort
	}
}

/** Event beforeinstallprompt untuk panduan instalasi manual. */
export const installPromptEvent = writable<unknown>(null);
export const isStandalone = writable(false);

if (typeof window !== 'undefined') {
	const standalone =
		window.matchMedia?.('(display-mode: standalone)').matches ||
		(navigator as Navigator & { standalone?: boolean }).standalone === true;
	isStandalone.set(!!standalone);
	window.addEventListener('beforeinstallprompt', (e) => {
		e.preventDefault();
		installPromptEvent.set(e);
	});
}
