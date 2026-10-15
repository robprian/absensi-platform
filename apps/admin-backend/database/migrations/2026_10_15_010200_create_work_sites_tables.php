<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_sites', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->integer('radius_meters')->default(100);
            $table->boolean('geofence_enabled')->default(true);
            $table->timestamps();
            $table->index(['geofence_enabled']);
        });

        // Mobile/field assignments: employee may be assigned to a site, or have
        // no site at all (location restriction optional).
        Schema::create('work_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('work_site_id')->nullable()->constrained('work_sites')->nullOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('unassigned_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'unassigned_at']);
        });

        // Work schedules. employee_id NULL = default template applied to all.
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0=Sun .. 6=Sat
            $table->string('shift_name', 50)->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('overnight')->default(false);
            $table->integer('break_minutes')->default(60);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['employee_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_schedules');
        Schema::dropIfExists('work_assignments');
        Schema::dropIfExists('work_sites');
    }
};
