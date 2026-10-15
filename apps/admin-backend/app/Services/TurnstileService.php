<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verifikasi token Cloudflare Turnstile secara server-side.
 *
 * Integrasi disiapkan bertahap:
 *  - `turnstile_site_key` (setting, grup security) → ditampilkan di widget login.
 *  - `TURNSTILE_SECRET_KEY` (env) → dipakai untuk verifikasi server-side.
 *  - `turnstile_enabled` (setting, grup security) → saklar utama.
 *
 * Bila belum dikonfigurasi (enabled=0 atau site/secret key kosong),
 * verifikasi dilewati (return true) agar login tetap berfungsi selama
 * masa persiapan integrasi.
 */
class TurnstileService
{
    public const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /** Apakah proteksi Turnstile aktif (siap diverifikasi). */
    public static function isEnabled(): bool
    {
        return (bool) SystemSetting::get('turnstile_enabled', false)
            && (bool) self::siteKey()
            && (bool) self::secretKey();
    }

    public static function siteKey(): ?string
    {
        $key = SystemSetting::get('turnstile_site_key', '') ?: config('services.turnstile.site_key');
        return $key ? (string) $key : null;
    }

    protected static function secretKey(): ?string
    {
        $key = config('services.turnstile.secret_key');
        return $key ? (string) $key : null;
    }

    /**
     * Verifikasi token dari widget. Return true bila integrasi belum
     * dikonfigurasi (mode persiapan) atau token valid menurut Cloudflare.
     */
    public static function verify(?string $token, ?string $remoteIp = null): bool
    {
        if (!self::isEnabled()) {
            return true; // belum disiapkan → jangan blokir login
        }

        if (!$token) {
            return false;
        }

        try {
            $response = Http::timeout((int) config('services.turnstile.timeout', 10))
                ->asForm()
                ->post(self::VERIFY_URL, [
                    'secret' => self::secretKey(),
                    'response' => $token,
                    'remoteip' => $remoteIp,
                ]);

            return (bool) ($response->json('success') ?? false);
        } catch (\Throwable $e) {
            Log::warning('Turnstile verify gagal', ['error' => $e->getMessage()]);
            return false; // fail-closed saat proteksi aktif
        }
    }
}
