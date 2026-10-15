/** Utilitas kamera + watermark absen yang dibakar ke dalam gambar (canvas). */
import type { AttendanceType } from './api';

export interface CaptureMeta {
	type: AttendanceType;
	employeeName: string;
	employeeCode: string;
	latitude: number;
	longitude: number;
	accuracy: number | null;
	address: string | null;
	/** Waktu pengambilan (WIB) untuk watermark. */
	takenAt: Date;
}

export interface GpsFix {
	latitude: number;
	longitude: number;
	accuracy: number | null;
}

export type GpsErrorKind =
	'denied' | 'timeout' | 'unavailable' | 'stale' | 'low_accuracy' | 'unsupported';

export class GpsError extends Error {
	kind: GpsErrorKind;
	constructor(kind: GpsErrorKind, message: string) {
		super(message);
		this.kind = kind;
	}
}

/** Minta posisi GPS sekali, tolak fix basi & akurasi buruk. */
export function requestGps(maxAccuracyMeters = 100, maxAgeMs = 30_000): Promise<GpsFix> {
	return new Promise((resolve, reject) => {
		if (typeof navigator === 'undefined' || !('geolocation' in navigator)) {
			return reject(new GpsError('unsupported', 'Perangkat tidak mendukung GPS.'));
		}
		navigator.geolocation.getCurrentPosition(
			(pos) => {
				const { latitude, longitude, accuracy } = pos.coords;
				if (
					!isFinite(latitude) ||
					!isFinite(longitude) ||
					Math.abs(latitude) > 90 ||
					Math.abs(longitude) > 180
				) {
					return reject(new GpsError('unavailable', 'Koordinat GPS tidak valid.'));
				}
				const age = Date.now() - pos.timestamp;
				if (age > maxAgeMs) {
					return reject(new GpsError('stale', 'Posisi GPS sudah basi. Coba lagi.'));
				}
				if (accuracy !== null && accuracy !== undefined && accuracy > maxAccuracyMeters) {
					return reject(
						new GpsError(
							'low_accuracy',
							`Akurasi GPS kurang baik (±${Math.round(accuracy)} m). Pindah ke area terbuka lalu coba lagi.`
						)
					);
				}
				resolve({ latitude, longitude, accuracy: accuracy ?? null });
			},
			(err) => {
				if (err.code === err.PERMISSION_DENIED) {
					reject(
						new GpsError(
							'denied',
							'Izin lokasi ditolak. Aktifkan izin lokasi untuk aplikasi ini di pengaturan perangkat.'
						)
					);
				} else if (err.code === err.TIMEOUT) {
					reject(
						new GpsError('timeout', 'Gagal mendapatkan lokasi (waktu habis). Pastikan GPS aktif.')
					);
				} else {
					reject(
						new GpsError('unavailable', 'Lokasi tidak tersedia. Pastikan GPS aktif lalu coba lagi.')
					);
				}
			},
			{ enableHighAccuracy: true, timeout: 20_000, maximumAge: 5_000 }
		);
	});
}

/** Buka kamera depan; kembalikan stream. */
export async function openFrontCamera(): Promise<MediaStream> {
	if (typeof navigator === 'undefined' || !navigator.mediaDevices?.getUserMedia) {
		throw new Error('Perangkat tidak mendukung kamera.');
	}
	try {
		return await navigator.mediaDevices.getUserMedia({
			video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 1280 } },
			audio: false
		});
	} catch {
		throw new Error(
			'Izin kamera ditolak. Aktifkan izin kamera untuk aplikasi ini di pengaturan perangkat.'
		);
	}
}

export function stopStream(stream: MediaStream | null) {
	stream?.getTracks().forEach((t) => t.stop());
}

const BULAN_SINGKAT = [
	'Jan',
	'Feb',
	'Mar',
	'Apr',
	'Mei',
	'Jun',
	'Jul',
	'Agu',
	'Sep',
	'Okt',
	'Nov',
	'Des'
];

function pad(n: number): string {
	return String(n).padStart(2, '0');
}

/**
 * Ambil frame dari <video> → resize (maks 1600px) → bakar watermark permanen
 * di bagian bawah → kembalikan dataURL JPEG (kualitas ~0.8).
 * Watermark TIDAK menutupi wajah (hanya ~22% bawah gambar).
 */
export async function captureWithWatermark(
	video: HTMLVideoElement,
	meta: CaptureMeta,
	maxDim = 1600,
	quality = 0.82
): Promise<string> {
	const vw = video.videoWidth;
	const vh = video.videoHeight;
	if (!vw || !vh) throw new Error('Kamera belum siap.');

	const scale = Math.min(1, maxDim / Math.max(vw, vh));
	const W = Math.round(vw * scale);
	const H = Math.round(vh * scale);

	const canvas = document.createElement('canvas');
	canvas.width = W;
	canvas.height = H;
	const ctx = canvas.getContext('2d');
	if (!ctx) throw new Error('Gagal memproses gambar.');

	// Kamera depan biasanya perlu di-mirror agar seperti cermin.
	ctx.save();
	ctx.translate(W, 0);
	ctx.scale(-1, 1);
	ctx.drawImage(video, 0, 0, W, H);
	ctx.restore();

	// ---- Watermark: overlay gelap semi-transparan di 24% bawah ----
	const bandH = Math.round(H * 0.24);
	const bandY = H - bandH;
	ctx.fillStyle = 'rgba(0,0,0,0.58)';
	ctx.fillRect(0, bandY, W, bandH);

	const padX = Math.round(W * 0.045);
	const baseFont = Math.max(13, Math.round(W / 34)); // skala mengikuti dimensi
	let y = bandY + Math.round(bandH * 0.16);

	const line = (text: string, size: number, weight: number, color = '#ffffff') => {
		ctx.fillStyle = color;
		ctx.font = `${weight} ${size}px 'Plus Jakarta Sans', 'Inter', system-ui, sans-serif`;
		ctx.textBaseline = 'top';
		ctx.fillText(text, padX, y);
		y += size * 1.32;
	};

		const d = new Date(meta.takenAt.toLocaleString('en-US', { timeZone: 'Asia/Jakarta' }));
	const tglText = `${d.getDate()} ${BULAN_SINGKAT[d.getMonth()]} ${d.getFullYear()}`;
	const jamText = `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;

	line(`ABSEN ${meta.type}`, Math.round(baseFont * 1.15), 800);
	line(`${meta.employeeName} | ${meta.employeeCode}`, baseFont, 600);
	line(`${tglText} • ${jamText} WIB`, Math.round(baseFont * 0.92), 500);
	if (meta.address) line(truncate(meta.address, 64), Math.round(baseFont * 0.92), 500);
	line(
		`GPS: ${meta.latitude.toFixed(6)}, ${meta.longitude.toFixed(6)}`,
		Math.round(baseFont * 0.88),
		500,
		'#e2e8f0'
	);
	line(
		meta.accuracy !== null
			? `Akurasi: ±${Math.round(meta.accuracy)} meter`
			: 'Akurasi: tidak diketahui',
		Math.round(baseFont * 0.88),
		500,
		'#e2e8f0'
	);

	// Ikon pin lokasi kecil di kanan atas band
	const pinSize = Math.round(baseFont * 1.1);
	ctx.fillStyle = '#34d399';
	ctx.beginPath();
	ctx.arc(W - padX - pinSize / 2, bandY + pinSize, pinSize / 2, 0, Math.PI * 2);
	ctx.fill();
	ctx.fillStyle = '#052e22';
	ctx.beginPath();
	ctx.arc(W - padX - pinSize / 2, bandY + pinSize, pinSize / 5, 0, Math.PI * 2);
	ctx.fill();

	return canvas.toDataURL('image/jpeg', quality);
}

function truncate(s: string, max: number): string {
	return s.length > max ? s.slice(0, max - 1) + '…' : s;
}

/** Konversi dataURL → Blob untuk FormData. */
export function dataUrlToBlob(dataUrl: string): Blob {
	const [head, body] = dataUrl.split(',');
	const mime = head?.match(/data:(.*?);/)?.[1] ?? 'image/jpeg';
	const bin = atob(body ?? '');
	const bytes = new Uint8Array(bin.length);
	for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
	return new Blob([bytes], { type: mime });
}
