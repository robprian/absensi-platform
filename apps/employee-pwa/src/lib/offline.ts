/** Antrean offline untuk pengiriman absen (IndexedDB). */

export interface PendingAttendance {
	id: string; // idempotency key
	type: 'MASUK' | 'KELUAR';
	photoDataUrl: string; // JPEG terkompresi + watermark
	latitude: number;
	longitude: number;
	accuracy: number | null;
	address: string | null;
	capturedAt: string; // ISO
	createdAt: number;
}

const DB_NAME = 'absensi-pwa';
const STORE = 'pending-attendance';

function openDb(): Promise<IDBDatabase> {
	return new Promise((resolve, reject) => {
		if (typeof indexedDB === 'undefined') return reject(new Error('IndexedDB tidak tersedia'));
		const req = indexedDB.open(DB_NAME, 1);
		req.onupgradeneeded = () => {
			if (!req.result.objectStoreNames.contains(STORE)) {
				req.result.createObjectStore(STORE, { keyPath: 'id' });
			}
		};
		req.onsuccess = () => resolve(req.result);
		req.onerror = () => reject(req.error ?? new Error('Gagal membuka database lokal'));
	});
}

function tx<T>(mode: IDBTransactionMode, fn: (store: IDBObjectStore) => IDBRequest<T>): Promise<T> {
	return openDb().then(
		(db) =>
			new Promise<T>((resolve, reject) => {
				const t = db.transaction(STORE, mode);
				const req = fn(t.objectStore(STORE));
				req.onsuccess = () => resolve(req.result);
				req.onerror = () => reject(req.error ?? new Error('Gagal mengakses antrean'));
				t.oncomplete = () => db.close();
			})
	);
}

export const offlineQueue = {
	add(item: PendingAttendance): Promise<void> {
		return tx('readwrite', (s) => s.add(item)).then(() => undefined);
	},
	list(): Promise<PendingAttendance[]> {
		return tx('readonly', (s) => s.getAll());
	},
	remove(id: string): Promise<void> {
		return tx('readwrite', (s) => s.delete(id)).then(() => undefined);
	},
	count(): Promise<number> {
		return tx('readonly', (s) => s.count());
	}
};

/** Status konektivitas reaktif sederhana. */
export function isOnline(): boolean {
	return typeof navigator === 'undefined' ? true : navigator.onLine;
}
