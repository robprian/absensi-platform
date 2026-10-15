<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\WorkAssignment;
use App\Models\WorkSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use Tests\Traits\MakesTestUsers;

class GeofenceTest extends TestCase
{
    use RefreshDatabase, MakesTestUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBase();
        $this->fakeTdocs();
    }

    protected function siteAndAssign($employee): WorkSite
    {
        $site = WorkSite::create([
            'name' => 'Kantor Pusat',
            'address' => 'Jl. Sudirman, Jakarta Selatan',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'radius_meters' => 100,
            'geofence_enabled' => true,
        ]);
        WorkAssignment::create([
            'employee_id' => $employee->id,
            'work_site_id' => $site->id,
            'assigned_at' => now(),
        ]);
        return $site;
    }

    protected function payload(string $key, float $lat, float $lng): array
    {
        return [
            'selfie' => UploadedFile::fake()->image('selfie.jpg', 640, 480),
            'lat' => $lat,
            'lng' => $lng,
            'accuracy' => 8,
            'idempotency_key' => $key,
        ];
    }

    public function test_clock_in_inside_geofence_succeeds(): void
    {
        $employee = $this->makeEmployee();
        $this->siteAndAssign($employee);

        $this->authPost($employee->user, '/api/v1/attendance/clock-in', $this->payload('g-1', -6.2089, 106.8457))
            ->assertStatus(201)
            ->assertJsonPath('verification_status', 'verified');
    }

    public function test_clock_in_outside_geofence_rejected_when_enforced(): void
    {
        $employee = $this->makeEmployee();
        $this->siteAndAssign($employee);
        SystemSetting::set('gps_policy', 'enforce', 'string', 'gps');

        // ~2km away from the site.
        $this->authPost($employee->user, '/api/v1/attendance/clock-in', $this->payload('g-2', -6.2299, 106.8299))
            ->assertStatus(422)
            ->assertJsonPath('code', 'geofence_rejected');
    }

    public function test_clock_in_outside_geofence_flagged_when_warn(): void
    {
        $employee = $this->makeEmployee();
        $this->siteAndAssign($employee);
        SystemSetting::set('gps_policy', 'warn', 'string', 'gps');

        $this->authPost($employee->user, '/api/v1/attendance/clock-in', $this->payload('g-3', -6.2299, 106.8299))
            ->assertStatus(201)
            ->assertJsonPath('verification_status', 'flagged');
    }

    public function test_low_accuracy_rejected(): void
    {
        $employee = $this->makeEmployee();
        SystemSetting::set('gps_accuracy_threshold_m', 50, 'integer', 'gps');

        $payload = $this->payload('g-4', -6.2088, 106.8456);
        $payload['accuracy'] = 250;

        $this->authPost($employee->user, '/api/v1/attendance/clock-in', $payload)
            ->assertStatus(422)
            ->assertJsonPath('code', 'low_accuracy');
    }

    public function test_invalid_coordinates_rejected(): void
    {
        $employee = $this->makeEmployee();

        $this->authPost($employee->user, '/api/v1/attendance/clock-in', $this->payload('g-5', 0, 0))
            ->assertStatus(422)
            ->assertJsonPath('code', 'invalid_coordinates');
    }

    public function test_employee_without_site_assignment_has_no_location_restriction(): void
    {
        $employee = $this->makeEmployee(); // no assignment = mobile field worker

        $this->authPost($employee->user, '/api/v1/attendance/clock-in', $this->payload('g-6', -7.2504, 112.7688))
            ->assertStatus(201)
            ->assertJsonPath('verification_status', 'verified');
    }
}
