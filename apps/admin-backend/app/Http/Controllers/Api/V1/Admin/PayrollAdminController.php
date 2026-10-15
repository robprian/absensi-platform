<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompensationPolicy;
use App\Models\EmployeeCompensationRule;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Services\AuditService;
use App\Services\PayrollService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PayrollAdminController extends Controller
{
    public function __construct(protected PayrollService $payroll) {}

    // ---------------------------------------------------------------
    // Policies (rate configuration)
    // ---------------------------------------------------------------

    public function policies(Request $request)
    {
        $this->authorize('configure', PayrollRun::class);
        return response()->json(CompensationPolicy::orderBy('priority')->get());
    }

    public function storePolicy(Request $request)
    {
        $this->authorize('configure', PayrollRun::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'policy_type' => ['required', Rule::in([
                'per_attendance', 'hourly', 'daily', 'monthly_base',
                'overtime', 'allowance', 'bonus', 'deduction',
            ])],
            'amount' => ['required', 'integer', 'min:0', 'max:100000000000'],
            'applies_when' => ['nullable', 'array'],
            'priority' => ['nullable', 'integer'],
            'active' => ['boolean'],
        ]);

        $policy = CompensationPolicy::create($data);
        AuditService::log('payroll.policy.created', $request->user(), 'compensation_policies', (string) $policy->id);

        return response()->json($policy, 201);
    }

    public function updatePolicy(Request $request, CompensationPolicy $policy)
    {
        $this->authorize('configure', PayrollRun::class);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'amount' => ['sometimes', 'integer', 'min:0', 'max:100000000000'],
            'applies_when' => ['nullable', 'array'],
            'priority' => ['nullable', 'integer'],
            'active' => ['boolean'],
        ]);

        $before = $policy->toArray();
        $policy->update($data);
        AuditService::log('payroll.policy.updated', $request->user(), 'compensation_policies', (string) $policy->id, $before);

        return response()->json($policy->fresh());
    }

    /**
     * Assign a policy to an employee (optionally overriding the amount).
     */
    public function assignRule(Request $request)
    {
        $this->authorize('configure', PayrollRun::class);

        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'compensation_policy_id' => ['required', 'exists:compensation_policies,id'],
            'custom_amount' => ['nullable', 'integer', 'min:0'],
            'effective_from' => ['required', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);

        $rule = EmployeeCompensationRule::updateOrCreate(
            [
                'employee_id' => $data['employee_id'],
                'compensation_policy_id' => $data['compensation_policy_id'],
            ],
            $data + ['active' => true]
        );

        AuditService::log('payroll.rule.assigned', $request->user(), 'employee_compensation_rules', (string) $rule->id);

        return response()->json($rule->load('policy'), 201);
    }

    // ---------------------------------------------------------------
    // Periods & runs
    // ---------------------------------------------------------------

    public function periods(Request $request)
    {
        $this->authorize('viewAny', PayrollRun::class);
        return response()->json(PayrollPeriod::withCount('runs')->orderByDesc('starts_on')->paginate(15));
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

        return response()->json($period, 201);
    }

    public function runs(Request $request)
    {
        $this->authorize('viewAny', PayrollRun::class);
        return response()->json(
            PayrollRun::with(['period', 'finalizer'])->orderByDesc('created_at')->paginate(15)
        );
    }

    public function storeRun(Request $request)
    {
        $this->authorize('generate', PayrollRun::class);

        $data = $request->validate(['payroll_period_id' => ['required', 'exists:payroll_periods,id']]);
        $run = PayrollRun::create($data + ['status' => PayrollRun::STATUS_DRAFT]);

        AuditService::log('payroll.run.created', $request->user(), 'payroll_runs', (string) $run->id);

        return response()->json($run->load('period'), 201);
    }

    public function showRun(Request $request, PayrollRun $run)
    {
        $this->authorize('view', $run);
        return response()->json($run->load(['period', 'items.employee', 'items.payslip', 'finalizer']));
    }

    public function generateDraft(Request $request, PayrollRun $run)
    {
        $this->authorize('generate', $run);
        $this->payroll->generateDraft($run);

        AuditService::log('payroll.draft.generated', $request->user(), 'payroll_runs', (string) $run->id);

        return response()->json(['message' => 'Draf penggajian dibuat.', 'run' => $run->fresh('items')]);
    }

    public function toReview(Request $request, PayrollRun $run)
    {
        $this->authorize('generate', $run);

        if ($run->status !== PayrollRun::STATUS_DRAFT) {
            return response()->json(['message' => 'Hanya draf yang bisa dikirim ke review.'], 422);
        }
        $run->update(['status' => PayrollRun::STATUS_REVIEW]);
        AuditService::log('payroll.run.review', $request->user(), 'payroll_runs', (string) $run->id);

        return response()->json(['message' => 'Draf dikirim ke review.']);
    }

    public function finalize(Request $request, PayrollRun $run)
    {
        $this->authorize('finalize', $run);
        $this->payroll->finalize($run, $request->user());

        return response()->json(['message' => 'Penggajian difinalisasi. Rate dikunci (snapshot).']);
    }

    public function publish(Request $request, PayrollRun $run)
    {
        $this->authorize('publish', $run);
        $this->payroll->publish($run, $request->user());

        return response()->json(['message' => 'Slip gaji dipublikasikan ke karyawan.']);
    }

    public function lock(Request $request, PayrollRun $run)
    {
        $this->authorize('lock', $run);
        $this->payroll->lock($run, $request->user());

        return response()->json(['message' => 'Periode penggajian dikunci.']);
    }
}
