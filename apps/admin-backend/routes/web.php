<?php

use App\Http\Controllers\Admin\AttendanceController as AdminAttendance;
use App\Http\Controllers\Admin\AuthController as AdminAuth;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\EmployeeController as AdminEmployee;
use App\Http\Controllers\Admin\PayrollController as AdminPayroll;
use App\Http\Controllers\Admin\ReimbursementController as AdminReimbursement;
use App\Http\Controllers\Admin\SettingsController as AdminSettings;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('admin.login'));

// ---- Admin session auth ----
Route::get('/admin/login', [AdminAuth::class, 'showLogin'])->name('admin.login')->middleware('guest');
Route::post('/admin/login', [AdminAuth::class, 'login'])->middleware(['guest', 'throttle:5,1']);
Route::post('/admin/logout', [AdminAuth::class, 'logout'])->name('admin.logout')->middleware('auth');

Route::prefix('admin')->name('admin.')->middleware(['auth', 'can:admin-access'])->group(function () {
    Route::get('/', [AdminDashboard::class, 'index'])->name('dashboard');

    // Karyawan
    Route::get('/karyawan', [AdminEmployee::class, 'index'])->name('employees.index');
    Route::get('/karyawan/baru', [AdminEmployee::class, 'create'])->name('employees.create');
    Route::post('/karyawan', [AdminEmployee::class, 'store'])->name('employees.store');
    Route::get('/karyawan/{employee}/ubah', [AdminEmployee::class, 'edit'])->name('employees.edit');
    Route::put('/karyawan/{employee}', [AdminEmployee::class, 'update'])->name('employees.update');
    Route::post('/karyawan/{employee}/nonaktifkan', [AdminEmployee::class, 'deactivate'])->name('employees.deactivate');

    // Absensi
    Route::get('/absensi', [AdminAttendance::class, 'index'])->name('attendance.index');
    Route::get('/absensi/{session}', [AdminAttendance::class, 'show'])->name('attendance.show');
    Route::get('/absensi/koreksi/daftar', [AdminAttendance::class, 'corrections'])->name('attendance.corrections');
    Route::post('/absensi/koreksi/{correction}/setujui', [AdminAttendance::class, 'approveCorrection'])->name('attendance.corrections.approve');
    Route::post('/absensi/koreksi/{correction}/tolak', [AdminAttendance::class, 'rejectCorrection'])->name('attendance.corrections.reject');
    Route::get('/absensi/ekspor/xlsx', [AdminAttendance::class, 'export'])->name('attendance.export');

    // Jadwal & lokasi
    Route::get('/jadwal', [AdminSettings::class, 'sites'])->name('sites.index');
    Route::post('/jadwal/lokasi', [AdminSettings::class, 'storeSite'])->name('sites.store');
    Route::put('/jadwal/lokasi/{workSite}', [AdminSettings::class, 'updateSite'])->name('sites.update');
    Route::delete('/jadwal/lokasi/{workSite}', [AdminSettings::class, 'destroySite'])->name('sites.destroy');
    Route::post('/jadwal/penugasan', [AdminSettings::class, 'assign'])->name('sites.assign');
    Route::post('/jadwal/jam', [AdminSettings::class, 'storeSchedule'])->name('schedules.store');

    // Penggajian
    Route::get('/penggajian', [AdminPayroll::class, 'index'])->name('payroll.index');
    Route::post('/penggajian/kebijakan', [AdminPayroll::class, 'storePolicy'])->name('payroll.policies.store');
    Route::put('/penggajian/kebijakan/{policy}', [AdminPayroll::class, 'updatePolicy'])->name('payroll.policies.update');
    Route::post('/penggajian/aturan', [AdminPayroll::class, 'assignRule'])->name('payroll.rules.assign');
    Route::post('/penggajian/periode', [AdminPayroll::class, 'storePeriod'])->name('payroll.periods.store');
    Route::post('/penggajian/run', [AdminPayroll::class, 'storeRun'])->name('payroll.runs.store');
    Route::get('/penggajian/run/{run}', [AdminPayroll::class, 'showRun'])->name('payroll.runs.show');
    Route::post('/penggajian/run/{run}/generate', [AdminPayroll::class, 'generateDraft'])->name('payroll.runs.generate');
    Route::post('/penggajian/run/{run}/review', [AdminPayroll::class, 'toReview'])->name('payroll.runs.review');
    Route::post('/penggajian/run/{run}/finalize', [AdminPayroll::class, 'finalize'])->name('payroll.runs.finalize');
    Route::post('/penggajian/run/{run}/publish', [AdminPayroll::class, 'publish'])->name('payroll.runs.publish');
    Route::post('/penggajian/run/{run}/lock', [AdminPayroll::class, 'lock'])->name('payroll.runs.lock');

    // Reimburse
    Route::get('/reimburse', [AdminReimbursement::class, 'index'])->name('reimbursements.index');
    Route::get('/reimburse/{reimbursement}', [AdminReimbursement::class, 'show'])->name('reimbursements.show');
    Route::post('/reimburse/{reimbursement}/status', [AdminReimbursement::class, 'transition'])->name('reimbursements.transition');

    // Laporan
    Route::get('/laporan', [AdminSettings::class, 'reports'])->name('reports.index');

    // Dokumen
    Route::get('/dokumen', [AdminSettings::class, 'documents'])->name('documents.index');
    Route::get('/dokumen/kesehatan-tdocs', [AdminSettings::class, 'tdocsHealth'])->name('documents.tdocs-health');

    // Pengaturan
    Route::get('/pengaturan', [AdminSettings::class, 'settings'])->name('settings.index');
    Route::put('/pengaturan', [AdminSettings::class, 'updateSettings'])->name('settings.update');

    // Audit log
    Route::get('/audit-log', [AdminSettings::class, 'auditLogs'])->name('audit.index');

    // Secure file proxy (signed URLs, session auth + permission check)
    Route::get('/berkas/{file}/pratinjau', [AdminAttendance::class, 'previewFile'])
        ->name('files.preview');
});
