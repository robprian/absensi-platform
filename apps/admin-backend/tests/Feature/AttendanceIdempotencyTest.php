<?php

namespace Tests\Feature;

use App\Models\AttendanceEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use Tests\Traits\MakesTestUsers;

class AttendanceIdempotencyTest extends TestCase
{
    use RefreshDatabase, MakesTestUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBase();
        $this->fakeTdocs();
    }

    protected function clockInPayload(string $key, array $overrides = []): array
    {
        return array_merge([
            'selfie' => UploadedFile::fake()->image('selfie.jpg', 640, 480),
            'lat' => -6.2088,
            'lng' => 106.8456,
            'accuracy' => 8,
            'idempotency_key' => $key,
        ], $overrides);
    }

    public function test_duplicate_key_returns_same_record_without_double_create(): void
    {
        $employee = $this->makeEmployee();
        $user = $employee->user;
        $idem = ['Idempotency-Key' => 'key-abc-123'];

        $first = $this->authPost($user, '/api/v1/attendance/clock-in', $this->clockInPayload('key-abc-123'), $idem);
        $first->assertStatus(201);
        $masukAt = $first->json('masuk_at');
        $this->assertNotEmpty($masukAt);

        $this->assertSame(1, AttendanceEvent::count());

        // Replay with the SAME key: 200, same record, no new event.
        $second = $this->authPost($user, '/api/v1/attendance/clock-in', $this->clockInPayload('key-abc-123'), $idem);
        $second->assertStatus(200);
        $second->assertHeader('X-Idempotent-Replayed', 'true');
        $this->assertSame($masukAt, $second->json('masuk_at'));
        $this->assertSame(1, AttendanceEvent::count());
    }

    public function test_key_reuse_with_different_payload_is_rejected(): void
    {
        $employee = $this->makeEmployee();
        $user = $employee->user;

        $this->authPost($user, '/api/v1/attendance/clock-in', $this->clockInPayload('key-xyz'))
            ->assertStatus(201);

        // Same key, different coordinates -> 422, and only one event exists.
        $this->authPost(
            $user,
            '/api/v1/attendance/clock-in',
            $this->clockInPayload('key-xyz', ['lat' => -6.3000, 'lng' => 106.9000])
        )->assertStatus(422)->assertJsonPath('code', 'idempotency_key_mismatch');

        $this->assertSame(1, AttendanceEvent::count());
    }

    public function test_duplicate_clock_in_with_new_key_is_rejected(): void
    {
        $employee = $this->makeEmployee();
        $user = $employee->user;

        $this->authPost($user, '/api/v1/attendance/clock-in', $this->clockInPayload('key-1'))
            ->assertStatus(201);

        // New idempotency key but same business action -> domain-level duplicate guard.
        $this->authPost($user, '/api/v1/attendance/clock-in', $this->clockInPayload('key-2'))
            ->assertStatus(422)
            ->assertJsonPath('code', 'duplicate_clock_in');

        $this->assertSame(1, AttendanceEvent::count());
    }

    public function test_missing_idempotency_key_is_rejected(): void
    {
        $employee = $this->makeEmployee();

        $payload = $this->clockInPayload('whatever');
        unset($payload['idempotency_key']);

        $this->authPost($employee->user, '/api/v1/attendance/clock-in', $payload)
            ->assertStatus(422)
            ->assertJsonPath('code', 'idempotency_key_required');
    }

    public function test_clock_out_flow_closes_session(): void
    {
        $employee = $this->makeEmployee();
        $user = $employee->user;

        $this->authPost($user, '/api/v1/attendance/clock-in', $this->clockInPayload('in-1'))
            ->assertStatus(201);

        $this->authPost($user, '/api/v1/attendance/clock-out', $this->clockInPayload('out-1'))
            ->assertStatus(201);

        $this->assertSame(2, AttendanceEvent::count());

        $status = $this->authGet($user, '/api/v1/attendance/today');
        $status->assertOk();
        $this->assertSame('selesai_bekerja', $status->json('status'));
        $this->assertNotEmpty($status->json('masuk_at'));
        $this->assertNotEmpty($status->json('keluar_at'));
        $this->assertIsInt($status->json('total_menit'));
    }

    public function test_clock_out_without_clock_in_is_rejected(): void
    {
        $employee = $this->makeEmployee();

        $this->authPost($employee->user, '/api/v1/attendance/clock-out', $this->clockInPayload('out-x'))
            ->assertStatus(422)
            ->assertJsonPath('code', 'missing_clock_in');
    }
}
