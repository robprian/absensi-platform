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
	} catch {
		publicSettings.set({ reimbursement_enabled: false, nama_perusahaan: 'Perusahaan' });
	} finally {
		settingsLoading.set(false);
		loaded = true;
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
