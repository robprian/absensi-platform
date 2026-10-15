<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompensationPolicy;
use App\Models\Employee;
use App\Models\EmployeeCompensationRule;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Services\AuditService;
use App\Services\PayrollService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PayrollController extends Controller
{
    public function __construct(protected PayrollService $payroll) {}

    public function index()
    {
        $this->authorize('viewAny', PayrollRun::class);

        $policies = CompensationPolicy::orderBy('priority')->get();
        $periods = PayrollPeriod::withCount('runs')->orderByDesc('starts_on')->paginate(10, ['*'], 'period_page');
        $runs = PayrollRun::with(['period', 'finalizer'])->orderByDesc('created_at')->paginate(10, ['*'], 'run_page');
        $employees = Employee::where('employment_status', 'aktif')->orderBy('name')->get();

        return view('admin.payroll.index', compact('policies', 'periods', 'runs', 'employees'));
    }

    public function storePolicy(Request $request)
    {
        $this->authorize('configure', PayrollRun::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'policy_type' => ['required', Rule::in(['per_attendance', 'hourly', 'daily', 'monthly_base', 'overtime', 'allowance', 'bonus', 'deduction'])],
            'amount' => ['required', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]);

        $policy = CompensationPolicy::create($data + ['active' => $request->boolean('active', true)]);
        AuditService::log('payroll.policy.created', $request->user(), 'compensation_policies', (string) $policy->id);

        return back()->with('ok', 'Kebijakan kompensasi ditambahkan.');
    }

    public function updatePolicy(Request $request, CompensationPolicy $policy)
    {
        $this->authorize('configure', PayrollRun::class);

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]);

        $before = $policy->toArray();
        $policy->update(['amount' => $data['amount'], 'active' => $request->boolean('active', true)]);
        AuditService::log('payroll.policy.updated', $request->user(), 'compensation_policies', (string) $policy->id, $before);

        return back()->with('ok', 'Kebijakan diperbarui.');
    }

    public function assignRule(Request $request)
    {
        $this->authorize('configure', PayrollRun::class);

        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'compensation_policy_id' => ['required', 'exists:compensation_policies,id'],
            'custom_amount' => ['nullable', 'integer', 'min:0'],
            'effective_from' => ['required', 'date'],
        ]);

        EmployeeCompensationRule::updateOrCreate(
            ['employee_id' => $data['employee_id'], 'compensation_policy_id' => $data['compensation_policy_id']],
            $data + ['active' => true]
        );

        AuditService::log('payroll.rule.assigned', $request->user(), 'employee_compensation_rules', $data['employee_id'] . ':' . $data['compensation_policy_id']);

        return back()->with('ok', 'Aturan kompensasi ditetapkan ke karyawan.');
    }

    public function storePeriod(Request $request)
    {
        $this->authorize('generate', PayrollRun::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'period_type' => ['required', Rule::in(['weekly', 'biweekly', 'monthly', 'custom'])],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ]);

        $period = PayrollPeriod::create($data + ['status' => 'open']);
        AuditService::log('payroll.period.created', $request->user(), 'payroll_periods', (string) $period->id);

        return back()->with('ok', 'Periode penggajian dibuat.');
    }

    public function storeRun(Request $request)
    {
        $this->authorize('generate', PayrollRun::class);

        $data = $request->validate(['payroll_period_id' => ['required', 'exists:payroll_periods,id']]);
        $run = PayrollRun::create($data + ['status' => PayrollRun::STATUS_DRAFT]);
        AuditService::log('payroll.run.created', $request->user(), 'payroll_runs', (string) $run->id);

        return redirect()->route('admin.payroll.runs.show', $run)->with('ok', 'Draf run dibuat. Klik "Generate Draf".');
    }

    public function showRun(PayrollRun $run)
    {
        $this->authorize('view', $run);
        $run->load(['period', 'items.employee', 'items.payslip', 'finalizer']);

        return view('admin.payroll.run', compact('run'));
    }

    public function generateDraft(Request $request, PayrollRun $run)
    {
        $this->authorize('generate', $run);
        $this->payroll->generateDraft($run);
        AuditService::log('payroll.draft.generated', $request->user(), 'payroll_runs', (string) $run->id);

        return back()->with('ok', 'Draf penggajian dibuat.');
    }

    public function toReview(Request $request, PayrollRun $run)
    {
        $this->authorize('generate', $run);

        if ($run->status !== PayrollRun::STATUS_DRAFT) {
            return back()->with('err', 'Hanya draf yang bisa dikirim ke review.');
        }
        $run->update(['status' => PayrollRun::STATUS_REVIEW]);
        AuditService::log('payroll.run.review', $request->user(), 'payroll_runs', (string) $run->id);

        return back()->with('ok', 'Draf dikirim ke review.');
    }

    public function finalize(Request $request, PayrollRun $run)
    {
        $this->authorize('finalize', $run);
        $this->payroll->finalize($run, $request->user());

        return back()->with('ok', 'Penggajian difinalisasi. Rate dikunci (snapshot).');
    }

    public function publish(Request $request, PayrollRun $run)
    {
        $this->authorize('publish', $run);
        $this->payroll->publish($run, $request->user());

        return back()->with('ok', 'Slip gaji dipublikasikan ke karyawan.');
    }

    public function lock(Request $request, PayrollRun $run)
    {
        $this->authorize('lock', $run);
        $this->payroll->lock($run, $request->user());

        return back()->with('ok', 'Periode penggajian dikunci.');
    }
}
