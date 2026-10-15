<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Employee::class);

        $employees = Employee::with(['department', 'user'])
            ->when($request->search, fn ($q, $s) => $q->where(
                fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('employee_code', 'like', "%$s%")
            ))
            ->when($request->status, fn ($q, $s) => $q->where('employment_status', $s))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.employees.index', compact('employees'));
    }

    public function create()
    {
        $this->authorize('create', Employee::class);
        $departments = Department::orderBy('name')->get();
        return view('admin.employees.form', ['employee' => new Employee(), 'departments' => $departments]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Employee::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'position' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'hire_date' => ['nullable', 'date'],
            'create_login' => ['nullable', 'boolean'],
            'login_email' => ['nullable', 'email', 'unique:users,email'],
            'login_password' => ['nullable', 'string', 'min:8'],
        ]);

        DB::transaction(function () use ($data, $request) {
            $userId = null;
            if (!empty($data['create_login'])) {
                $request->validate([
                    'login_email' => ['required', 'email', 'unique:users,email'],
                    'login_password' => ['required', 'string', 'min:8'],
                ]);
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['login_email'],
                    'phone' => $data['phone'] ?? null,
                    'password' => Hash::make($data['login_password']),
                ]);
                $user->roles()->attach(Role::where('name', Role::EMPLOYEE)->firstOrFail()->id);
                $userId = $user->id;
            }

            $code = $this->nextEmployeeCode();
            $employee = Employee::create([
                'employee_code' => $code,
                'user_id' => $userId,
                'department_id' => $data['department_id'] ?? null,
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'position' => $data['position'] ?? null,
                'hire_date' => $data['hire_date'] ?? null,
                'employment_status' => 'aktif',
            ]);

            AuditService::log('employee.created', $request->user(), 'employees', (string) $employee->id);
        });

        return redirect()->route('admin.employees.index')->with('ok', 'Karyawan ditambahkan.');
    }

    public function edit(Employee $employee)
    {
        $this->authorize('update', $employee);
        $departments = Department::orderBy('name')->get();
        return view('admin.employees.form', compact('employee', 'departments'));
    }

    public function update(Request $request, Employee $employee)
    {
        $this->authorize('update', $employee);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'position' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'employment_status' => ['required', Rule::in(['aktif', 'nonaktif', 'keluar'])],
            'hire_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $before = $employee->toArray();
        $employee->update($data);
        AuditService::log('employee.updated', $request->user(), 'employees', (string) $employee->id, $before);

        return redirect()->route('admin.employees.index')->with('ok', 'Data karyawan diperbarui.');
    }

    public function deactivate(Request $request, Employee $employee)
    {
        $this->authorize('delete', $employee);

        $employee->update(['employment_status' => 'nonaktif']);
        $employee->user?->tokens()->delete();
        $employee->delete();

        AuditService::log('employee.deactivated', $request->user(), 'employees', (string) $employee->id);

        return redirect()->route('admin.employees.index')->with('ok', 'Karyawan dinonaktifkan.');
    }

    protected function nextEmployeeCode(): string
    {
        $last = Employee::withTrashed()->orderByDesc('id')->value('employee_code');
        $num = $last ? ((int) preg_replace('/\D/', '', $last)) + 1 : 1;
        do {
            $code = 'EMP-' . str_pad((string) $num, 3, '0', STR_PAD_LEFT);
            $num++;
        } while (Employee::withTrashed()->where('employee_code', $code)->exists());
        return $code;
    }
}
