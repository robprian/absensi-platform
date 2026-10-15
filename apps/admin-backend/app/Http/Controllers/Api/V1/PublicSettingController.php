<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\TurnstileService;
use Illuminate\Http\Request;

class PublicSettingController extends Controller
{
    /**
     * GET /api/v1/settings/public — non-sensitive settings for the PWA.
     * Matches the PWA PublicSettings contract.
     */
    public function public(Request $request)
    {
        return response()->json([
            'reimbursement_enabled' => SystemSetting::isReimbursementEnabled(),
            'nama_perusahaan' => SystemSetting::get('company_name', 'PT Contoh Sejahtera'),
            'brand' => [
                'logo_url' => $this->brandUrl('brand_logo_path'),
                'icon_192_url' => $this->brandUrl('brand_icon_192_path'),
                'icon_512_url' => $this->brandUrl('brand_icon_512_path'),
                'favicon_url' => $this->brandUrl('brand_favicon_path'),
                'primary_color' => SystemSetting::get('brand_primary_color', '#059669'),
            ],
            'turnstile' => [
                'enabled' => TurnstileService::isEnabled(),
                'site_key' => TurnstileService::siteKey(),
            ],
        ]);
    }

    private function brandUrl(string $key): ?string
    {
        $path = SystemSetting::get($key, '');
        if (!$path) return null;
        return \Illuminate\Support\Facades\Storage::url($path);
    }
}
