<?php

use App\Http\Controllers\Api\V1\Admin\AttendanceAdminController;
use App\Http\Controllers\Api\V1\Admin\AuditLogController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\EmployeeController;
use App\Http\Controllers\Api\V1\Admin\FileController;
use App\Http\Controllers\Api\V1\Admin\PayrollAdminController;
use App\Http\Controllers\Api\V1\Admin\ReimbursementAdminController;
use App\Http\Controllers\Api\V1\Admin\SettingController;
use App\Http\Controllers\Api\V1\Admin\WorkSiteController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PayslipController;
use App\Http\Controllers\Api\V1\PublicSettingController;
use App\Http\Controllers\Api\V1\ReimbursementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 — Employee PWA + Admin clients (Sanctum Bearer tokens)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // Public: login with rate limiting.
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {

        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        // ---- Attendance (employee) ----
        Route::post('attendance/clock-in', [AttendanceController::class, 'clockIn'])
            ->middleware(['idempotency', 'throttle:30,1']);
        Route::post('attendance/clock-out', [AttendanceController::class, 'clockOut'])
            ->middleware(['idempotency', 'throttle:30,1']);
        Route::get('attendance/today', [AttendanceController::class, 'today']);
        Route::post('attendance/geocode', [AttendanceController::class, 'geocode'])
            ->middleware('throttle:30,1');
        Route::get('attendance/history', [AttendanceController::class, 'history']);

        // ---- Payslips (employee: finalized + published only) ----
        Route::get('payslips', [PayslipController::class, 'index']);
        Route::get('payslips/{payslip}', [PayslipController::class, 'show']);
        Route::get('payslips/{payslip}/download-url', [PayslipController::class, 'downloadUrl']);
        Route::get('payroll/payslips/{payslip}/download', [PayslipController::class, 'download'])
            ->name('api.v1.payslips.download');

        // ---- Reimbursements (employee) ----
        Route::middleware('reimbursement.enabled')->group(function () {
            Route::post('reimbursements', [ReimbursementController::class, 'store'])
                ->middleware(['idempotency', 'throttle:20,1']);
        });
        Route::get('reimbursements', [ReimbursementController::class, 'index']);
        Route::get('reimbursements/{reimbursement}', [ReimbursementController::class, 'show']);

        // ---- Public (non-sensitive) settings ----
        Route::get('settings/public', [PublicSettingController::class, 'public']);

        // ---- Notifications ----
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/{notification}/read', [NotificationController::class, 'read']);

        // ---- Admin ----
        Route::prefix('admin')->middleware('role:super_admin,hr_admin,payroll_officer,supervisor')->group(function () {

            Route::get('dashboard/stats', [DashboardController::class, 'stats']);

            Route::apiResource('employees', EmployeeController::class);

            Route::get('attendance', [AttendanceAdminController::class, 'index']);
            Route::get('attendance/export', [AttendanceAdminController::class, 'export']);
            Route::get('attendance/corrections', [AttendanceAdminController::class, 'corrections']);
            Route::post('attendance/corrections/{correction}/approve', [AttendanceAdminController::class, 'approveCorrection']);
            Route::post('attendance/corrections/{correction}/reject', [AttendanceAdminController::class, 'rejectCorrection']);
            Route::get('attendance/{session}', [AttendanceAdminController::class, 'show']);

            Route::get('payroll/policies', [PayrollAdminController::class, 'policies']);
            Route::post('payroll/policies', [PayrollAdminController::class, 'storePolicy']);
            Route::patch('payroll/policies/{policy}', [PayrollAdminController::class, 'updatePolicy']);
            Route::post('payroll/rules/assign', [PayrollAdminController::class, 'assignRule']);
            Route::get('payroll/periods', [PayrollAdminController::class, 'periods']);
            Route::post('payroll/periods', [PayrollAdminController::class, 'storePeriod']);
            Route::get('payroll/runs', [PayrollAdminController::class, 'runs']);
            Route::post('payroll/runs', [PayrollAdminController::class, 'storeRun']);
            Route::get('payroll/runs/{run}', [PayrollAdminController::class, 'showRun']);
            Route::post('payroll/runs/{run}/generate', [PayrollAdminController::class, 'generateDraft']);
            Route::post('payroll/runs/{run}/review', [PayrollAdminController::class, 'toReview']);
            Route::post('payroll/runs/{run}/finalize', [PayrollAdminController::class, 'finalize']);
            Route::post('payroll/runs/{run}/publish', [PayrollAdminController::class, 'publish']);
            Route::post('payroll/runs/{run}/lock', [PayrollAdminController::class, 'lock']);

            Route::get('reimbursements', [ReimbursementAdminController::class, 'index']);
            Route::get('reimbursements/{reimbursement}', [ReimbursementAdminController::class, 'show']);
            Route::post('reimbursements/{reimbursement}/transition', [ReimbursementAdminController::class, 'transition']);

            Route::get('work-sites', [WorkSiteController::class, 'index']);
            Route::post('work-sites', [WorkSiteController::class, 'store']);
            Route::patch('work-sites/{workSite}', [WorkSiteController::class, 'update']);
            Route::delete('work-sites/{workSite}', [WorkSiteController::class, 'destroy']);
            Route::post('work-assignments/assign', [WorkSiteController::class, 'assign']);
            Route::get('schedules', [WorkSiteController::class, 'schedules']);
            Route::post('schedules', [WorkSiteController::class, 'storeSchedule']);

            Route::get('settings', [SettingController::class, 'index']);
            Route::put('settings', [SettingController::class, 'update']);

            Route::get('audit-logs', [AuditLogController::class, 'index']);

            // Secure file proxy (signed URLs, 10 min).
            Route::get('files/{file}/preview', [FileController::class, 'preview'])
                ->name('api.v1.admin.files.preview');
            Route::get('files/{file}/download', [FileController::class, 'download'])
                ->name('api.v1.admin.files.download');
            Route::get('tdocs/health', [FileController::class, 'tdocsHealth']);
        });
    });
});
