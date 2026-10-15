/** Utilitas format: Rupiah, tanggal Indonesia, jam Asia/Jakarta. */

export function formatRupiah(nilai: number | null | undefined): string {
	if (nilai === null || nilai === undefined || Number.isNaN(nilai)) return 'Rp 0';
	const bulat = Math.round(nilai);
	return 'Rp ' + bulat.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

const NAMA_HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
const NAMA_BULAN = [
	'Januari',
	'Februari',
	'Maret',
	'April',
	'Mei',
	'Juni',
	'Juli',
	'Agustus',
	'September',
	'Oktober',
	'November',
	'Desember'
];
const NAMA_BULAN_SINGKAT = [
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

/** Konversi ISO UTC ke komponen waktu Asia/Jakarta. */
export function toWibParts(iso: string | Date): {
	hari: string;
	tanggal: number;
	bulan: string;
	bulanSingkat: string;
	tahun: number;
	jam: string;
	menit: string;
	detik: string;
} {
	const d = typeof iso === 'string' ? new Date(iso) : iso;
	const parts = new Intl.DateTimeFormat('id-ID', {
		timeZone: 'Asia/Jakarta',
		weekday: 'long',
		day: 'numeric',
		month: 'long',
		year: 'numeric',
		hour: '2-digit',
		minute: '2-digit',
		second: '2-digit',
		hour12: false
	})
		.formatToParts(d)
		.reduce<Record<string, string>>((acc, p) => {
			acc[p.type] = p.value;
			return acc;
		}, {});
	return {
		hari: parts.weekday ?? '',
		tanggal: Number(parts.day),
		bulan: parts.month ?? '',
		bulanSingkat:
			NAMA_BULAN_SINGKAT[
				Number(d.toLocaleString('en-US', { timeZone: 'Asia/Jakarta', month: 'numeric' })) - 1
			] ?? '',
		tahun: Number(parts.year),
		jam: parts.hour ?? '00',
		menit: parts.minute ?? '00',
		detik: parts.second ?? '00'
	};
}

export function formatTanggalPanjang(iso: string | Date): string {
	const p = toWibParts(iso);
	return `${p.hari}, ${p.tanggal} ${p.bulan} ${p.tahun}`;
}

export function formatTanggalSingkat(iso: string | Date): string {
	const p = toWibParts(iso);
	return `${p.tanggal} ${p.bulanSingkat} ${p.tahun}`;
}

/** "14 Okt 2026 • 08:00:24 WIB" — dipakai untuk watermark & tampilan. */
export function formatTanggalJamWib(iso: string | Date): string {
	const p = toWibParts(iso);
	return `${p.tanggal} ${p.bulanSingkat} ${p.tahun} • ${p.jam}:${p.menit}:${p.detik} WIB`;
}

export function formatJam(iso: string | Date): string {
	const p = toWibParts(iso);
	return `${p.jam}:${p.menit}`;
}

/** Hari ini dalam WIB sebagai "Rabu, 14 Oktober 2026". */
export function hariIniWib(): string {
	return formatTanggalPanjang(new Date());
}

/** Durasi menit → "4 jam 32 menit" / "45 menit" / "8 jam". */
export function formatDurasi(menitTotal: number): string {
	const m = Math.max(0, Math.floor(menitTotal));
	const jam = Math.floor(m / 60);
	const menit = m % 60;
	if (jam === 0) return `${menit} menit`;
	if (menit === 0) return `${jam} jam`;
	return `${jam} jam ${menit} menit`;
}

/** Detik → "04:32:10" untuk penghitung live. */
export function formatStopwatch(detikTotal: number): string {
	const s = Math.max(0, Math.floor(detikTotal));
	const j = Math.floor(s / 3600);
	const m = Math.floor((s % 3600) / 60);
	const d = s % 60;
	const pad = (n: number) => String(n).padStart(2, '0');
	return `${pad(j)}:${pad(m)}:${pad(d)}`;
}

/** Nama bulan Indonesia (indeks 0–11). */
export function namaBulan(idx: number): string {
	return NAMA_BULAN[((idx % 12) + 12) % 12] ?? '';
}

/** Nama hari Indonesia (indeks 0=Minggu). */
export function namaHari(idx: number): string {
	return NAMA_HARI[((idx % 7) + 7) % 7] ?? '';
}

export { NAMA_HARI, NAMA_BULAN, NAMA_BULAN_SINGKAT };
