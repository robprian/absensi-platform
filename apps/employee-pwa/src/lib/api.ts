import { get } from 'svelte/store';
import { token, clearSession, setSession, type Employee } from './auth';

const BASE: string = (import.meta.env.VITE_API_BASE_URL as string | undefined) ?? '/api';
const V1 = `${BASE.replace(/\/+$/, '')}/v1`;

export class ApiError extends Error {
	status: number;
	data: unknown;
	constructor(status: number, message: string, data: unknown = null) {
		super(message);
		this.status = status;
		this.data = data;
	}
}

async function request<T>(
	path: string,
	init: RequestInit = {},
	idempotencyKey?: string
): Promise<T> {
	const t = get(token);
	const headers: Record<string, string> = {
		...(init.headers as Record<string, string> | undefined)
	};
	if (!(init.body instanceof FormData)) headers['Content-Type'] = 'application/json';
	if (t) headers['Authorization'] = `Bearer ${t}`;
	if (idempotencyKey) headers['Idempotency-Key'] = idempotencyKey;
	headers['Accept'] = 'application/json';

	const res = await fetch(`${V1}${path}`, { ...init, headers });
	if (res.status === 401) {
		clearSession();
		// Jangan hard-reload kalau sudah di /login — cegah loop refresh.
		if (typeof window !== 'undefined' && window.location.pathname !== '/login') {
			window.location.href = '/login';
		}
		throw new ApiError(401, 'Sesi berakhir. Silakan masuk kembali.');
	}
	const text = await res.text();
	const data = text ? safeJson(text) : null;
	if (!res.ok) {
		const msg = extractMessage(data, res.status);
		throw new ApiError(res.status, msg, data);
	}
	return data as T;
}

function safeJson(text: string): unknown {
	try {
		return JSON.parse(text);
	} catch {
		return null;
	}
}

function extractMessage(data: unknown, status: number): string {
	if (data && typeof data === 'object') {
		const d = data as Record<string, unknown>;
		if (typeof d.message === 'string') return d.message;
		if (d.errors && typeof d.errors === 'object') {
			const first = Object.values(d.errors as Record<string, unknown[]>)[0];
			if (Array.isArray(first) && typeof first[0] === 'string') return first[0];
		}
	}
	const fallback: Record<number, string> = {
		400: 'Permintaan tidak valid.',
		403: 'Akses ditolak.',
		404: 'Data tidak ditemukan.',
		409: 'Data sudah tercatat sebelumnya.',
		422: 'Data yang dikirim belum lengkap.',
		429: 'Terlalu banyak percobaan. Coba lagi nanti.',
		500: 'Terjadi kesalahan pada server.'
	};
	return fallback[status] ?? 'Terjadi kesalahan. Coba lagi.';
}

export const get_ = <T>(path: string) => request<T>(path, { method: 'GET' });
export const postJson = <T>(path: string, body: unknown, idempotencyKey?: string) =>
	request<T>(path, { method: 'POST', body: JSON.stringify(body) }, idempotencyKey);
export const postForm = <T>(path: string, form: FormData, idempotencyKey?: string) =>
	request<T>(path, { method: 'POST', body: form }, idempotencyKey);

// ---------------------------------------------------------------------------
// Tipe domain (kontrak API backend, lihat SPEC.md)
// ---------------------------------------------------------------------------

export type AttendanceType = 'MASUK' | 'KELUAR';

export interface TodayAttendance {
	status: 'belum_absen' | 'sedang_bekerja' | 'selesai_bekerja';
	masuk_at?: string | null;
	keluar_at?: string | null;
	total_menit?: number | null;
	lokasi_masuk?: string | null;
	ada_antrean?: boolean;
}

export interface AttendanceHistoryItem {
	id: string;
	tanggal: string; // YYYY-MM-DD
	masuk_at: string | null;
	keluar_at: string | null;
	total_menit: number | null;
	status: 'hadir' | 'terlambat' | 'pulang_cepat' | 'lembur' | 'tidak_lengkap' | 'libur';
	lokasi: string | null;
}

export interface PayslipSummary {
	id: string;
	periode: string; // "September 2026"
	tanggal_mulai: string;
	tanggal_selesai: string;
	total_diterima: number; // integer IDR
	status_bayar: 'belum' | 'dibayar';
}

export interface PayslipDetail extends PayslipSummary {
	nomor_dokumen: string;
	gaji_pokok: number;
	insentif_kehadiran: number;
	lembur: number;
	tunjangan: number;
	potongan: number;
	reimburse_disetujui: number;
	rincian: { label: string; nominal: number; tipe: 'tambah' | 'kurang' }[];
}

export type ReimburseStatus = 'menunggu' | 'disetujui' | 'ditolak' | 'dibayar';

export interface Reimbursement {
	id: string;
	kategori: string;
	nominal: number;
	tanggal: string;
	keterangan: string;
	status: ReimburseStatus;
	ada_bukti: boolean;
	dibuat_at: string;
}

export interface AppNotification {
	id: string;
	judul: string;
	isi: string;
	dibuat_at: string;
	sudah_dibaca: boolean;
}

export interface PublicSettings {
	reimbursement_enabled: boolean;
	nama_perusahaan: string;
}

// ---------------------------------------------------------------------------
// API
// ---------------------------------------------------------------------------

export const api = {
	login(identifier: string, password: string) {
		return postJson<{ token: string; employee: Employee }>('/auth/login', {
			identifier,
			password
		});
	},
	logout() {
		return postJson<{ ok: boolean }>('/auth/logout', {}).finally(() => clearSession());
	},
	me() {
		return get_<{ employee: Employee }>('/auth/me');
	},
	publicSettings() {
		return get_<PublicSettings>('/settings/public');
	},
	todayAttendance() {
		return get_<TodayAttendance>('/attendance/today');
	},
	clockIn(form: FormData, idempotencyKey: string) {
		return postForm<{ ok: boolean; masuk_at: string }>(
			'/attendance/clock-in',
			form,
			idempotencyKey
		);
	},
	clockOut(form: FormData, idempotencyKey: string) {
		return postForm<{ ok: boolean; keluar_at: string; total_menit: number }>(
			'/attendance/clock-out',
			form,
			idempotencyKey
		);
	},
	/** Reverse geocode best-effort via backend; boleh gagal tanpa menggagalkan absen. */
	geocode(lat: number, lng: number) {
		return postJson<{ address: string | null }>('/attendance/geocode', {
			latitude: lat,
			longitude: lng
		});
	},
	history(bulan: string) {
		return get_<{ items: AttendanceHistoryItem[] }>(`/attendance/history?bulan=${bulan}`);
	},
	payslips() {
		return get_<{ items: PayslipSummary[] }>('/payslips');
	},
	payslipDetail(id: string) {
		return get_<PayslipDetail>(`/payslips/${id}`);
	},
	payslipDownloadUrl(id: string) {
		return get_<{ url: string; expires_at: string }>(`/payslips/${id}/download-url`);
	},
	reimbursements() {
		return get_<{ items: Reimbursement[] }>('/reimbursements');
	},
	submitReimbursement(form: FormData) {
		return postForm<{ ok: boolean; id: string }>('/reimbursements', form);
	},
	notifications() {
		return get_<{ items: AppNotification[] }>('/notifications');
	},
	markNotificationRead(id: string) {
		return postJson<{ ok: boolean }>(`/notifications/${id}/read`, {});
	}
};

export function refreshSession(emp: Employee, t: string) {
	setSession(t, emp);
}

/** Bangun kunci idempotensi unik per pengiriman absen. */
export function newIdempotencyKey(): string {
	if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) return crypto.randomUUID();
	return `idem-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}
