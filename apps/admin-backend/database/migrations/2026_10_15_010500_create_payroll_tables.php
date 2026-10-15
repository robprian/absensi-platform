<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150); // e.g. "Oktober 2026"
            $table->string('period_type', 20); // weekly, biweekly, monthly, custom
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 20)->default('open'); // open, finalized, locked
            $table->timestamps();
            $table->index(['starts_on', 'ends_on']);
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->string('status', 20)->default('draft'); // draft, review, finalized, locked
            $table->text('notes')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('rate_snapshot')->nullable(); // full rate snapshot at finalization
            $table->timestamps();
            $table->index(['status']);
        });

        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->bigInteger('base_salary')->default(0);
            $table->bigInteger('attendance_comp')->default(0);
            $table->bigInteger('overtime')->default(0);
            $table->bigInteger('allowances')->default(0);
            $table->bigInteger('bonuses')->default(0);
            $table->bigInteger('reimbursements')->default(0);
            $table->bigInteger('deductions')->default(0);
            $table->bigInteger('gross')->default(0);
            $table->bigInteger('net')->default(0);
            $table->integer('work_days')->default(0);
            $table->integer('total_minutes')->default(0);
            $table->json('rate_snapshot')->nullable(); // per-employee rate snapshot
            $table->string('status', 20)->default('draft');
            $table->timestamps();
            $table->unique(['payroll_run_id', 'employee_id']);
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_item_id')->unique()->constrained('payroll_items')->cascadeOnDelete();
            $table->string('reference_code', 50)->unique(); // e.g. SLIP-2026-10-0001
            $table->foreignId('pdf_file_id')->nullable()->constrained('stored_files')->nullOnDelete();
            $table->boolean('published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('payroll_periods');
    }
};
