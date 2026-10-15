<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\AuditService;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', SystemSetting::class);
        return response()->json(
            SystemSetting::orderBy('group_name')->orderBy('key')->get()->groupBy('group_name')
        );
    }

    public function update(Request $request)
    {
        $this->authorize('update', SystemSetting::class);

        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string', 'exists:system_settings,key'],
            'settings.*.value' => ['nullable'],
        ]);

        foreach ($data['settings'] as $item) {
            $setting = SystemSetting::where('key', $item['key'])->firstOrFail();
            $before = $setting->value;

            // Type-safe coercion.
            $value = match ($setting->value_type) {
                'integer' => (string) (int) $item['value'],
                'boolean' => !empty($item['value']) && $item['value'] !== '0' ? '1' : '0',
                'json' => json_encode($item['value']),
                default => (string) $item['value'],
            };

            // Guard gps_policy values.
            if ($setting->key === 'gps_policy' && !in_array($value, ['enforce', 'warn', 'off'], true)) {
                return response()->json(['message' => 'Nilai gps_policy tidak valid.'], 422);
            }

            $setting->update(['value' => $value]);
            \Illuminate\Support\Facades\Cache::forget("setting:{$setting->key}");

            AuditService::log(
                'setting.updated', $request->user(), 'system_settings', (string) $setting->id,
                ['key' => $setting->key, 'value' => $before],
                ['key' => $setting->key, 'value' => $value]
            );
        }

        return response()->json(['message' => 'Pengaturan disimpan.']);
    }
}
