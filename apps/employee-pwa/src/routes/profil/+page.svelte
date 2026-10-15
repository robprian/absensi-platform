<script lang="ts">
	import { goto } from '$app/navigation';
	import { LogOut, User, Phone, MapPin, Building2, Briefcase, Download } from 'lucide-svelte';
	import { api } from '$lib/api';
	import { employee, clearSession } from '$lib/auth';
	import { isStandalone } from '$lib/settings';

	let loggingOut = false;

	async function keluar() {
		if (!confirm('Yakin ingin keluar dari aplikasi?')) return;
		loggingOut = true;
		try {
			await api.logout();
		} catch {
			clearSession();
		} finally {
			loggingOut = false;
			await goto('/login');
		}
	}

	$: emp = $employee;
</script>

<svelte:head>
	<title>Profil — Absensi Kerja</title>
</svelte:head>

<div class="px-5 pb-6 pt-5">
	<h1 class="text-2xl font-extrabold">Profil</h1>

	<section class="mt-4 rounded-3xl bg-white p-6 shadow-sm">
		<div class="flex items-center gap-4">
			<div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-emerald-100">
				<User size={32} class="text-emerald-700" />
			</div>
			<div class="min-w-0">
				<p class="truncate text-xl font-extrabold">{emp?.nama ?? '–'}</p>
				<p class="text-sm text-slate-500">{emp?.employee_id ?? '–'}</p>
			</div>
		</div>

		<dl class="mt-6 space-y-4 text-[15px]">
			{#if emp?.telepon}
				<div class="flex items-center gap-3">
					<Phone size={18} class="shrink-0 text-slate-400" />
					<div>
						<dt class="text-xs text-slate-400">Telepon</dt>
						<dd class="font-semibold">{emp.telepon}</dd>
					</div>
				</div>
			{/if}
			{#if emp?.jabatan}
				<div class="flex items-center gap-3">
					<Briefcase size={18} class="shrink-0 text-slate-400" />
					<div>
						<dt class="text-xs text-slate-400">Jabatan</dt>
						<dd class="font-semibold">{emp.jabatan}</dd>
					</div>
				</div>
			{/if}
			{#if emp?.departemen}
				<div class="flex items-center gap-3">
					<Building2 size={18} class="shrink-0 text-slate-400" />
					<div>
						<dt class="text-xs text-slate-400">Departemen</dt>
						<dd class="font-semibold">{emp.departemen}</dd>
					</div>
				</div>
			{/if}
			{#if emp?.lokasi_kerja}
				<div class="flex items-center gap-3">
					<MapPin size={18} class="shrink-0 text-slate-400" />
					<div>
						<dt class="text-xs text-slate-400">Lokasi Kerja</dt>
						<dd class="font-semibold">{emp.lokasi_kerja}</dd>
					</div>
				</div>
			{/if}
		</dl>
	</section>

	<section class="mt-4 space-y-3">
		<a
			href="/install"
			class="pressable flex min-h-[60px] items-center gap-3 rounded-2xl bg-white px-5 shadow-sm"
		>
			<Download size={20} class="text-emerald-600" />
			<span class="font-bold">Pasang Aplikasi {$isStandalone ? '(sudah terpasang)' : ''}</span>
		</a>
		<button
			type="button"
			on:click={keluar}
			disabled={loggingOut}
			class="pressable flex min-h-[60px] w-full items-center justify-center gap-2 rounded-2xl bg-red-50 font-bold text-red-700 disabled:opacity-60"
		>
			<LogOut size={20} />
			{loggingOut ? 'Keluar…' : 'Keluar'}
		</button>
	</section>

	<p class="mt-6 text-center text-xs text-slate-400">Absensi Kerja v0.1.0</p>
</div>
