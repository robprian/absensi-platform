<script lang="ts">
	import { onMount, onDestroy } from 'svelte';
	import { page } from '$app/stores';
	import { goto } from '$app/navigation';
	import {
		Camera,
		MapPin,
		Check,
		RotateCcw,
		TriangleAlert,
		Loader2,
		WifiOff,
		ChevronLeft
	} from 'lucide-svelte';
	import { api, ApiError, newIdempotencyKey, type AttendanceType } from '$lib/api';
	import { employee } from '$lib/auth';
	import {
		openFrontCamera,
		stopStream,
		requestGps,
		captureWithWatermark,
		dataUrlToBlob,
		GpsError,
		type GpsFix
	} from '$lib/camera';
	import { offlineQueue, isOnline } from '$lib/offline';
	import { formatTanggalJamWib } from '$lib/format';

	type Step = 'izin' | 'kamera' | 'konfirmasi' | 'mengirim' | 'selesai';

	const type: AttendanceType = $page.url.searchParams.get('type') === 'KELUAR' ? 'KELUAR' : 'MASUK';

	let step: Step = 'izin';
	let error = '';
	let errorKind: string | null = null;

	let stream: MediaStream | null = null;
	let videoEl: HTMLVideoElement | null = null;
	let gps: GpsFix | null = null;
	let gpsMencari = false;
	let gpsAkurasi: number | null = null;
	let address: string | null = null;
	let now = new Date();
	let clockTimer: ReturnType<typeof setInterval> | undefined;

	let photoDataUrl: string | null = null;
	let idemKey = newIdempotencyKey();
	let offlineQueued = false;
	let kirimError = '';

	const nama = $employee?.nama ?? 'Karyawan';
	const kode = $employee?.employee_id ?? '-';

	onMount(() => {
		clockTimer = setInterval(() => (now = new Date()), 1000);
		return () => {
			if (clockTimer) clearInterval(clockTimer);
		};
	});

	onDestroy(() => stopStream(stream));

	async function mulaiIzin() {
		error = '';
		errorKind = null;
		try {
			// Kamera dulu (prompt berurutan lebih jelas di HP), lalu GPS.
			stream = await openFrontCamera();
			step = 'kamera';
			// GPS menyusul; pratinjau tetap tampil sambil menunggu fix.
			// onUpdate: tampilkan status live (mencari → akurasi membaik).
			gpsMencari = true;
			try {
				gps = await requestGps(50, 30_000, (fix) => {
					gps = fix;
					gpsAkurasi = fix.accuracy;
				});
				void resolveAddress();
			} catch (e) {
				if (e instanceof GpsError) {
					errorKind = e.kind;
					error = e.message;
				} else {
					error = 'Gagal mendapatkan lokasi.';
				}
			} finally {
				gpsMencari = false;
			}
		} catch (e) {
			error = e instanceof Error ? e.message : 'Gagal mengakses kamera.';
		}
	}

	async function cobaGpsLagi() {
		error = '';
		errorKind = null;
		gpsMencari = true;
		try {
			gps = await requestGps(50, 30_000, (fix) => {
				gps = fix;
				gpsAkurasi = fix.accuracy;
			});
			void resolveAddress();
		} catch (e) {
			if (e instanceof GpsError) {
				errorKind = e.kind;
				error = e.message;
			} else {
				error = 'Gagal mendapatkan lokasi.';
			}
		} finally {
			gpsMencari = false;
		}
	}

	async function resolveAddress() {
		if (!gps) return;
		try {
			const r = await api.geocode(gps.latitude, gps.longitude);
			address = r.address;
		} catch {
			address = null; // best-effort; absen tetap jalan
		}
	}

	// Pasang stream ke <video> setelah elemen dirender.
	$: if (videoEl && stream && !videoEl.srcObject) {
		videoEl.srcObject = stream;
		void videoEl.play().catch(() => {});
	}

	async function ambilFoto() {
		if (!videoEl || !gps) return;
		try {
			photoDataUrl = await captureWithWatermark(videoEl, {
				type,
				employeeName: nama,
				employeeCode: kode,
				latitude: gps.latitude,
				longitude: gps.longitude,
				accuracy: gps.accuracy,
				address,
				takenAt: new Date()
			});
			stopStream(stream);
			stream = null;
			step = 'konfirmasi';
		} catch (e) {
			error = e instanceof Error ? e.message : 'Gagal mengambil foto.';
		}
	}

	function ambilUlang() {
		photoDataUrl = null;
		idemKey = newIdempotencyKey();
		void mulaiIzin();
	}

	async function kirim() {
		if (!photoDataUrl || !gps) return;
		kirimError = '';
		step = 'mengirim';
		const payload = {
			id: idemKey,
			type,
			photoDataUrl,
			latitude: gps.latitude,
			longitude: gps.longitude,
			accuracy: gps.accuracy,
			address,
			capturedAt: new Date().toISOString(),
			createdAt: Date.now()
		};

		if (!isOnline()) {
			await offlineQueue.add(payload);
			offlineQueued = true;
			step = 'selesai';
			return;
		}

		const form = new FormData();
		form.append('photo', dataUrlToBlob(photoDataUrl), `absen-${type.toLowerCase()}.jpg`);
		form.append('type', type);
		form.append('latitude', String(gps.latitude));
		form.append('longitude', String(gps.longitude));
		if (gps.accuracy !== null) form.append('accuracy', String(gps.accuracy));
		if (address) form.append('address', address);
		form.append('captured_at', payload.capturedAt);

		try {
			if (type === 'MASUK') await api.clockIn(form, idemKey);
			else await api.clockOut(form, idemKey);
			step = 'selesai';
		} catch (e) {
			if (e instanceof ApiError && e.status === 409) {
				// Duplikat idempotensi → sudah tercatat di server.
				step = 'selesai';
				return;
			}
			// Gagal jaringan saat kirim → antrekan offline, JANGAN klaim terkirim.
			if (!isOnline()) {
				await offlineQueue.add(payload);
				offlineQueued = true;
				step = 'selesai';
				return;
			}
			kirimError = e instanceof ApiError ? e.message : 'Gagal mengirim absen.';
			step = 'konfirmasi';
		}
	}

	function selesai() {
		void goto('/');
	}
</script>

<svelte:head>
	<title>Absen {type === 'MASUK' ? 'Masuk' : 'Keluar'} — Absensi Kerja</title>
</svelte:head>

<div class="flex min-h-dvh flex-col bg-slate-950 text-white">
	<div class="safe-top flex items-center gap-2 px-4 pt-3">
		<button
			type="button"
			on:click={() => goto('/')}
			class="pressable flex h-11 w-11 items-center justify-center rounded-xl bg-white/10"
			aria-label="Kembali"
		>
			<ChevronLeft size={22} />
		</button>
		<h1 class="text-lg font-bold">Absen {type === 'MASUK' ? 'Masuk' : 'Keluar'}</h1>
	</div>

	{#if step === 'izin'}
		<div class="flex flex-1 flex-col justify-center px-6 pb-10">
			<div
				class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-3xl bg-emerald-600"
			>
				<Camera size={36} class="text-white" />
			</div>
			<h2 class="text-center text-2xl font-extrabold">Izin Kamera & Lokasi</h2>
			<div class="mt-6 space-y-4 text-slate-300">
				<div class="flex gap-3 rounded-2xl bg-white/5 p-4">
					<Camera size={24} class="shrink-0 text-emerald-400" />
					<p class="text-sm">
						<strong class="text-white">Kamera depan</strong> digunakan untuk swafoto kehadiran Anda.
					</p>
				</div>
				<div class="flex gap-3 rounded-2xl bg-white/5 p-4">
					<MapPin size={24} class="shrink-0 text-sky-400" />
					<p class="text-sm">
						<strong class="text-white">Lokasi GPS</strong> dicatat sebagai bukti kehadiran dan ditampilkan
						pada foto.
					</p>
				</div>
			</div>
			{#if error}
				<div
					class="mt-4 flex items-start gap-2 rounded-2xl bg-red-500/15 p-4 text-sm text-red-200"
					role="alert"
				>
					<TriangleAlert size={18} class="mt-0.5 shrink-0" />
					<span>{error}</span>
				</div>
			{/if}
			<button
				type="button"
				on:click={mulaiIzin}
				class="pressable mt-8 min-h-[64px] w-full rounded-2xl bg-emerald-600 text-xl font-bold shadow-lg"
			>
				Izinkan & Lanjutkan
			</button>
			<p class="mt-4 text-center text-xs text-slate-400">
				Lokasi hanya diambil saat Anda absen, tidak dilacak terus-menerus.
			</p>
		</div>
	{:else if step === 'kamera'}
		<div class="flex flex-1 flex-col px-4 pb-6 pt-4">
			<div class="relative mx-auto w-full max-w-md flex-1 overflow-hidden rounded-3xl bg-black">
				<video
					bind:this={videoEl}
					playsinline
					muted
					autoplay
					class="h-full w-full -scale-x-100 object-cover"
					aria-label="Pratinjau kamera depan"
				></video>
				<!-- Pratinjau watermark (HTML) — versi permanen dibakar via canvas -->
				<div class="pointer-events-none absolute inset-x-0 bottom-0 bg-black/55 px-4 py-3">
					<p class="text-sm font-extrabold tracking-wide">ABSEN {type}</p>
					<p class="text-xs font-semibold text-slate-100">{nama} | {kode}</p>
					<p class="text-[11px] text-slate-200">{formatTanggalJamWib(now)}</p>
					{#if address}
						<p class="truncate text-[11px] text-slate-200">{address}</p>
					{/if}
					<p class="text-[11px] text-slate-300">
						{#if gps}
							GPS: {gps.latitude.toFixed(6)}, {gps.longitude.toFixed(6)}
							{gps.accuracy !== null ? `• ±${Math.round(gps.accuracy)} m` : ''}
							{#if gpsMencari}<span class="text-amber-300"> • mempertajam…</span>{/if}
						{:else if gpsMencari}
							<span class="inline-flex items-center gap-1">
								<Loader2 size={12} class="animate-spin" /> Mencari lokasi GPS…
							</span>
						{:else}
							Mencari lokasi…
						{/if}
					</p>
				</div>
			</div>

			{#if error}
				<div
					class="mx-auto mt-3 w-full max-w-md rounded-2xl bg-red-500/15 p-4 text-sm text-red-200"
					role="alert"
				>
					<div class="flex items-start gap-2">
						<TriangleAlert size={18} class="mt-0.5 shrink-0" />
						<span>{error}</span>
					</div>
					{#if errorKind === 'timeout' || errorKind === 'unavailable'}
						<button
							type="button"
							on:click={cobaGpsLagi}
							class="pressable mt-3 min-h-[48px] w-full rounded-xl bg-white/10 font-bold"
						>
							{#if gpsMencari}
								<span class="inline-flex items-center gap-2">
									<Loader2 size={18} class="animate-spin" /> Mencari GPS…
									{gpsAkurasi !== null ? `(±${Math.round(gpsAkurasi)} m)` : ''}
								</span>
							{:else}
								Coba Lagi
							{/if}
						</button>
					{/if}
				</div>
			{/if}

			<div class="mx-auto mt-4 w-full max-w-md">
				<button
					type="button"
					on:click={ambilFoto}
					disabled={!gps}
					class="pressable flex min-h-[68px] w-full items-center justify-center gap-3 rounded-2xl bg-emerald-600 text-xl font-bold shadow-lg disabled:opacity-40"
				>
					<Camera size={26} />
					{gps ? 'Ambil Foto' : 'Menunggu GPS…'}
				</button>
			</div>
		</div>
	{:else if step === 'konfirmasi'}
		<div class="flex flex-1 flex-col px-4 pb-6 pt-4">
			<div class="mx-auto w-full max-w-md overflow-hidden rounded-3xl bg-black">
				{#if photoDataUrl}
					<img src={photoDataUrl} alt="Foto absen dengan watermark" class="w-full" />
				{/if}
			</div>
			<p class="mx-auto mt-3 w-full max-w-md text-center text-sm text-slate-300">
				Periksa foto Anda. Watermark lokasi sudah tertanam permanen pada foto.
			</p>
			{#if kirimError}
				<div
					class="mx-auto mt-3 w-full max-w-md flex items-start gap-2 rounded-2xl bg-red-500/15 p-4 text-sm text-red-200"
					role="alert"
				>
					<TriangleAlert size={18} class="mt-0.5 shrink-0" />
					<span>{kirimError}</span>
				</div>
			{/if}
			<div class="mx-auto mt-4 grid w-full max-w-md grid-cols-2 gap-3">
				<button
					type="button"
					on:click={ambilUlang}
					class="pressable flex min-h-[60px] items-center justify-center gap-2 rounded-2xl bg-white/10 font-bold"
				>
					<RotateCcw size={20} /> Ambil Ulang
				</button>
				<button
					type="button"
					on:click={kirim}
					class="pressable flex min-h-[60px] items-center justify-center gap-2 rounded-2xl bg-emerald-600 text-lg font-bold shadow-lg"
				>
					<Check size={22} /> Kirim Absen
				</button>
			</div>
		</div>
	{:else if step === 'mengirim'}
		<div class="flex flex-1 flex-col items-center justify-center px-6">
			<Loader2 size={48} class="animate-spin text-emerald-400" />
			<p class="mt-4 text-lg font-bold">Mengirim absen…</p>
			<p class="mt-1 text-sm text-slate-400">Mohon tunggu, jangan tutup aplikasi.</p>
		</div>
	{:else if step === 'selesai'}
		<div class="flex flex-1 flex-col items-center justify-center px-6 pb-10 text-center">
			<div class="flex h-24 w-24 items-center justify-center rounded-full bg-emerald-600">
				<Check size={52} class="text-white" />
			</div>
			<h2 class="mt-6 text-2xl font-extrabold">
				Absen {type === 'MASUK' ? 'Masuk' : 'Keluar'} Berhasil
			</h2>
			{#if offlineQueued}
				<div
					class="mt-4 flex items-center gap-2 rounded-2xl bg-amber-500/15 px-5 py-3 text-sm font-semibold text-amber-200"
				>
					<WifiOff size={18} />
					<span>Anda offline — absen disimpan & akan dikirim otomatis (menunggu sinkron).</span>
				</div>
			{:else}
				<p class="mt-2 text-slate-300">
					Tercatat pada {formatTanggalJamWib(now)}
				</p>
			{/if}
			<button
				type="button"
				on:click={selesai}
				class="pressable mt-8 min-h-[60px] w-full max-w-md rounded-2xl bg-emerald-600 text-lg font-bold"
			>
				Kembali ke Beranda
			</button>
		</div>
	{/if}
</div>
