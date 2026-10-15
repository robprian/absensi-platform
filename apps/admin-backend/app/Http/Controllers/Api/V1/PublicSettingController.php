<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
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
        ]);
    }
}
