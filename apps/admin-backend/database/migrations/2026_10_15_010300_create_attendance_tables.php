<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('work_date'); // Asia/Jakarta calendar date of the shift
            $table->foreignId('work_site_id')->nullable()->constrained('work_sites')->nullOnDelete();
            $table->string('status', 20)->default('open'); // open, closed, exception
            $table->integer('total_minutes')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'work_date']); // one session per employee per workday
            $table->index(['work_date', 'status']);
        });

        Schema::create('attendance_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_session_id')->constrained('attendance_sessions')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('event_type', 10); // MASUK, KELUAR
            $table->timestamp('server_at'); // authoritative server receipt time (UTC)
            $table->timestamp('client_captured_at')->nullable(); // reported evidence only
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy_meters', 8, 2);
            $table->text('address')->nullable();
            $table->json('device_info')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('idempotency_key', 100)->unique();
            $table->string('verification_status', 20)->default('verified'); // verified, flagged, rejected
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->index(['attendance_session_id', 'event_type']);
            $table->index(['server_at']);
        });

        Schema::create('attendance_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_event_id')->constrained('attendance_events')->cascadeOnDelete();
            $table->string('evidence_type', 30); // selfie_original, selfie_watermarked
            $table->foreignId('stored_file_id')->constrained('stored_files')->cascadeOnDelete();
            $table->json('client_metadata')->nullable();
            $table->timestamps();
            $table->unique(['attendance_event_id', 'evidence_type']);
        });

        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_session_id')->constrained('attendance_sessions')->cascadeOnDelete();
            $table->foreignId('attendance_event_id')->nullable()->constrained('attendance_events')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40); // missing_clock_out, wrong_time, manual_event
            $table->json('payload'); // proposed changes
            $table->text('reason');
            $table->string('status', 20)->default('pending'); // pending, approved, rejected
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status']);
        });

        Schema::create('break_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_session_id')->constrained('attendance_sessions')->cascadeOnDelete();
            $table->string('break_type', 30)->default('istirahat');
            $table->integer('minutes');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('break_records');
        Schema::dropIfExists('attendance_corrections');
        Schema::dropIfExists('attendance_evidence');
        Schema::dropIfExists('attendance_events');
        Schema::dropIfExists('attendance_sessions');
    }
};
