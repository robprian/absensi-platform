<script lang="ts">
	import { goto } from '$app/navigation';
	import { Clock3, Phone, Lock, Eye, EyeOff, TriangleAlert } from 'lucide-svelte';
	import { api, ApiError } from '$lib/api';
	import { setSession } from '$lib/auth';
	import { publicSettings } from '$lib/settings';

	let identifier = '';
	let password = '';
	let showPassword = false;
	let loading = false;
	let error = '';

	// Cloudflare Turnstile (anti-bot). Widget hanya tampil bila diaktifkan admin.
	let turnstileToken = '';
	let turnstileEl: HTMLDivElement | null = null;
	let turnstileRendered = false;

	$: turnstileCfg = $publicSettings?.turnstile;

	function renderTurnstile(siteKey: string) {
		if (turnstileRendered || !turnstileEl) return;
		const w = (window as unknown as { turnstile?: TurnstileApi }).turnstile;
		if (!w) return;
		turnstileRendered = true;
		w.render(turnstileEl, {
			sitekey: siteKey,
			callback: (token: string) => (turnstileToken = token),
			'expired-callback': () => (turnstileToken = ''),
			'error-callback': () => (turnstileToken = '')
		});
	}

	function ensureTurnstileScript(siteKey: string) {
		if (turnstileRendered) return;
		const w = (window as unknown as { turnstile?: TurnstileApi }).turnstile;
		if (w) {
			renderTurnstile(siteKey);
			return;
		}
		if (document.querySelector('script[data-turnstile]')) return;
		const s = document.createElement('script');
		s.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
		s.async = true;
		s.defer = true;
		s.dataset.turnstile = '1';
		s.onload = () => renderTurnstile(siteKey);
		document.head.appendChild(s);
	}

	$: if (turnstileCfg?.enabled && turnstileCfg.site_key && turnstileEl) {
		ensureTurnstileScript(turnstileCfg.site_key);
	}

	async function masuk() {
		error = '';
		if (!identifier.trim() || !password) {
			error = 'Isi nomor HP / ID karyawan dan kata sandi.';
			return;
		}
		if (turnstileCfg?.enabled && !turnstileToken) {
			error = 'Selesaikan verifikasi keamanan dulu.';
			return;
		}
		loading = true;
		try {
			const res = await api.login(identifier.trim(), password, turnstileToken || undefined);
			setSession(res.token, res.employee);
			await goto('/');
		} catch (e) {
			error = e instanceof ApiError ? e.message : 'Gagal masuk. Periksa koneksi lalu coba lagi.';
		} finally {
			loading = false;
		}
	}

	interface TurnstileApi {
		render: (el: HTMLElement, opts: Record<string, unknown>) => string;
	}
</script>

<svelte:head>
	<title>Masuk — Absensi Kerja</title>
</svelte:head>

<div class="flex min-h-dvh flex-col bg-white px-6 pb-8 pt-16">
	<div
		class="mx-auto mb-8 flex h-20 w-20 items-center justify-center rounded-3xl bg-emerald-600 shadow-lg"
	>
		<Clock3 size={40} class="text-white" />
	</div>
	<h1 class="text-center text-3xl font-extrabold text-slate-900">Selamat Datang</h1>
	<p class="mt-2 text-center text-slate-500">Masuk untuk mencatat kehadiran Anda</p>

	<form on:submit|preventDefault={masuk} class="mt-10 space-y-5">
		<div>
			<label for="identifier" class="mb-2 block text-sm font-bold text-slate-700">
				Nomor HP / ID Karyawan
			</label>
			<div class="relative">
				<Phone
					size={20}
					class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"
				/>
				<input
					id="identifier"
					type="text"
					bind:value={identifier}
					autocomplete="username"
					inputmode="text"
					placeholder="cth: 08123456789 / EMP-001"
					class="min-h-[56px] w-full rounded-2xl border-2 border-slate-200 bg-slate-50 py-4 pl-12 pr-4 text-lg text-slate-900 outline-none transition-colors duration-200 placeholder:text-slate-400 focus:border-emerald-600 focus:bg-white"
				/>
			</div>
		</div>

		<div>
			<label for="password" class="mb-2 block text-sm font-bold text-slate-700">Kata Sandi</label>
			<div class="relative">
				<Lock
					size={20}
					class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"
				/>
				<input
					id="password"
					type={showPassword ? 'text' : 'password'}
					bind:value={password}
					autocomplete="current-password"
					placeholder="Masukkan kata sandi"
					class="min-h-[56px] w-full rounded-2xl border-2 border-slate-200 bg-slate-50 py-4 pl-12 pr-14 text-lg text-slate-900 outline-none transition-colors duration-200 placeholder:text-slate-400 focus:border-emerald-600 focus:bg-white"
				/>
				<button
					type="button"
					on:click={() => (showPassword = !showPassword)}
					class="pressable absolute right-2 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-xl"
					aria-label={showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'}
				>
					{#if showPassword}
						<EyeOff size={20} class="text-slate-500" />
					{:else}
						<Eye size={20} class="text-slate-500" />
					{/if}
				</button>
			</div>
		</div>

		{#if error}
			<div
				class="flex items-start gap-2 rounded-2xl bg-red-50 p-4 text-sm font-medium text-red-700"
				role="alert"
			>
				<TriangleAlert size={18} class="mt-0.5 shrink-0" />
				<span>{error}</span>
			</div>
		{/if}

		{#if turnstileCfg?.enabled && turnstileCfg.site_key}
			<div class="flex justify-center">
				<div bind:this={turnstileEl}></div>
			</div>
		{/if}

		<button
			type="submit"
			disabled={loading}
			class="pressable min-h-[60px] w-full rounded-2xl bg-emerald-600 text-xl font-bold text-white shadow-lg shadow-emerald-600/25 disabled:opacity-60"
		>
			{loading ? 'Memeriksa…' : 'Masuk'}
		</button>
	</form>

	<p class="mt-8 text-center text-sm text-slate-400">
		Lupa kata sandi? Hubungi HRD / admin perusahaan.
	</p>
</div>
