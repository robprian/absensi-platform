/** Sinkronisasi antrean absen offline dengan kunci idempotensi yang SAMA. */
import { api } from '$lib/api';
import { offlineQueue, isOnline, type PendingAttendance } from '$lib/offline';
import { dataUrlToBlob } from '$lib/camera';

export interface SyncResult {
	sent: number;
	failed: number;
}

export async function syncPendingAttendance(): Promise<SyncResult> {
	if (!isOnline()) return { sent: 0, failed: 0 };
	const items = await offlineQueue.list();
	let sent = 0;
	let failed = 0;
	for (const item of items) {
		try {
			await sendOne(item);
			await offlineQueue.remove(item.id);
			sent++;
		} catch (e) {
			// 409 (duplikat idempotensi) = sudah tercatat di server → hapus dari antrean.
			if (e && typeof e === 'object' && 'status' in e && (e as { status: number }).status === 409) {
				await offlineQueue.remove(item.id);
				sent++;
			} else {
				failed++;
			}
		}
	}
	return { sent, failed };
}

function sendOne(item: PendingAttendance): Promise<unknown> {
	const form = new FormData();
	form.append('photo', dataUrlToBlob(item.photoDataUrl), `absen-${item.type.toLowerCase()}.jpg`);
	form.append('type', item.type);
	form.append('latitude', String(item.latitude));
	form.append('longitude', String(item.longitude));
	if (item.accuracy !== null) form.append('accuracy', String(item.accuracy));
	if (item.address) form.append('address', item.address);
	form.append('captured_at', item.capturedAt);

	if (item.type === 'MASUK') return api.clockIn(form, item.id);
	return api.clockOut(form, item.id);
}
