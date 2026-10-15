<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use App\Services\AuditService;
use App\Services\TurnstileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/v1/auth/login — employee login, returns Sanctum token.
     *
     * Body: {identifier, password} — identifier may be email, phone, or employee code.
     * Response: {token, employee: {...}} matching the PWA Employee contract.
     */
    public function login(Request $request)
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'turnstile_token' => ['nullable', 'string', 'max:2048'],
        ]);

        // Anti-bot: verifikasi Turnstile bila proteksi aktif.
        if (TurnstileService::isEnabled()
            && !TurnstileService::verify($data['turnstile_token'] ?? null, $request->ip())
        ) {
            throw ValidationException::withMessages([
                'identifier' => ['Verifikasi keamanan gagal. Coba lagi.'],
            ]);
        }

        $user = $this->findUserByIdentifier($data['identifier']);

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'identifier' => ['Identitas atau kata sandi salah.'],
            ]);
        }

        $employee = $user->employee;
        if (!$employee || !$employee->isActive()) {
            throw ValidationException::withMessages([
                'identifier' => ['Akun ini tidak terhubung ke data karyawan aktif.'],
            ]);
        }

        // Revoke old tokens for a clean single-device session.
        $user->tokens()->delete();
        $token = $user->createToken($data['device_name'] ?? 'pwa')->plainTextToken;

        AuditService::log('auth.login', $user);

        return response()->json([
            'token' => $token,
            'employee' => $this->formatEmployee($employee->loadMissing('department')),
        ]);
    }

    /**
     * POST /api/v1/auth/logout — revokes the current token.
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        $request->user()->currentAccessToken()->delete();
        AuditService::log('auth.logout', $user);

        return response()->json(['ok' => true]);
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(Request $request)
    {
        $user = $request->user()->load('employee.department');

        $employee = $user->employee;
        if (!$employee || !$employee->isActive()) {
            abort(403, 'Akun tidak terhubung ke data karyawan aktif.');
        }

        return response()->json([
            'employee' => $this->formatEmployee($employee),
        ]);
    }

    /**
     * Identifier may be a user email/phone, or an employee code/phone/email.
     */
    protected function findUserByIdentifier(string $identifier): ?User
    {
        $identifier = trim($identifier);

        $user = User::where('email', $identifier)
            ->orWhere('phone', $identifier)
            ->first();

        if ($user) {
            return $user;
        }

        $employee = Employee::where('employee_code', $identifier)
            ->orWhere('phone', $identifier)
            ->orWhere('email', $identifier)
            ->with('user')
            ->first();

        return $employee?->user;
    }

    /**
     * Employee payload matching the PWA contract (api.ts Employee).
     * ids are strings.
     */
    public static function formatEmployee(Employee $employee): array
    {
        $lokasiKerja = $employee->workAssignments()
            ->whereNull('unassigned_at')
            ->with('workSite')
            ->first()?->workSite?->name;

        return [
            'id' => (string) $employee->id,
            'employee_id' => $employee->employee_code,
            'nama' => $employee->name,
            'telepon' => $employee->phone,
            'email' => $employee->email,
            'departemen' => $employee->department?->name,
            'jabatan' => $employee->position,
            'lokasi_kerja' => $lokasiKerja,
            'status' => $employee->employment_status,
        ];
    }
}
