<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // [key, value, type, group, description]
            ['app_name', 'AbsensiKu', 'string', 'general', 'Nama aplikasi'],
            ['app_timezone', 'Asia/Jakarta', 'string', 'general', 'Zona waktu tampilan'],
            ['company_name', 'PT Contoh Sejahtera', 'string', 'company', 'Nama perusahaan (slip gaji)'],
            ['company_address', 'Jl. Sudirman No. 1, Jakarta Selatan', 'string', 'company', 'Alamat perusahaan'],
            ['company_phone', '021-1234567', 'string', 'company', 'Telepon perusahaan'],

            ['gps_accuracy_threshold_m', '50', 'integer', 'gps', 'Batas maksimal akurasi GPS (meter)'],
            ['gps_stale_threshold_seconds', '300', 'integer', 'gps', 'Batas usia fix GPS sebelum ditolak (detik)'],
            ['gps_policy', 'enforce', 'string', 'gps', 'Kebijakan geofence: enforce | warn | off'],

            ['break_minutes_default', '60', 'integer', 'attendance', 'Pengurang istirahat default (menit)'],
            ['overtime_threshold_minutes', '480', 'integer', 'attendance', 'Batas jam kerja normal sebelum lembur (menit)'],
            ['late_tolerance_minutes', '15', 'integer', 'attendance', 'Toleransi keterlambatan (menit)'],
            ['attendance_photo_max_kb', '5120', 'integer', 'attendance', 'Ukuran maksimal foto absensi (KB)'],

            ['reimbursement_enabled', '1', 'boolean', 'reimbursement', 'Fitur reimburse aktif/nonaktif'],

            ['payroll_lock_days', '0', 'integer', 'payroll', 'Kunci otomatis periode setelah N hari (0 = manual)'],
        ];

        foreach ($settings as [$key, $value, $type, $group, $desc]) {
            SystemSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'value_type' => $type, 'group_name' => $group, 'description' => $desc]
            );
        }
    }
}
