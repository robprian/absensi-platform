<?php
namespace Tests\Feature;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Role;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tests\Traits\MakesTestUsers;
class DbgTest extends TestCase {
    use RefreshDatabase, MakesTestUsers;
    protected function setUp(): void { parent::setUp(); $this->seedBase(); }
    public function test_dbg(): void {
        Route::get('/dbg-hdr', function (\Illuminate\Http\Request $r) {
            return ['hdr' => substr($r->header('Authorization', '-'), 0, 25), 'uid' => $r->user()?->id];
        })->middleware('auth:sanctum');
        $employee = $this->makeEmployee();
        $officer = $this->makeUserWithRole(Role::PAYROLL_OFFICER);
        $headers = $this->authHeaders($officer);
        $eh = $this->authHeaders($employee->user);
        dump('officer hdr', substr($headers['Authorization'], 0, 25));
        dump('emp hdr', substr($eh['Authorization'], 0, 25));
        dump('token map', PersonalAccessToken::query()->pluck('tokenable_id', 'id')->all());
        $period = PayrollPeriod::create(['name'=>'O','period_type'=>'monthly','starts_on'=>'2026-10-01','ends_on'=>'2026-10-31','status'=>'open']);
        $run = PayrollRun::create(['payroll_period_id'=>$period->id,'status'=>'draft']);
        $this->postJson("/api/v1/admin/payroll/runs/{$run->id}/generate", [], $headers)->assertOk();
        dump('after generate', $this->getJson('/dbg-hdr', $eh)->json());
        $this->assertTrue(true);
    }
}
