<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Compensation policies. ALL amounts are integer IDR — never float.
        Schema::create('compensation_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('policy_type', 30); // per_attendance, hourly, daily, monthly_base, overtime, allowance, bonus, deduction
            $table->bigInteger('amount'); // integer rupiah
            $table->json('applies_when')->nullable(); // eligibility rules, e.g. {"min_minutes":480,"requires_clock_out":true}
            $table->integer('priority')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['policy_type', 'active']);
        });

        Schema::create('employee_compensation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('compensation_policy_id')->constrained('compensation_policies')->cascadeOnDelete();
            $table->bigInteger('custom_amount')->nullable(); // override, integer rupiah
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['employee_id', 'compensation_policy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_compensation_rules');
        Schema::dropIfExists('compensation_policies');
    }
};
