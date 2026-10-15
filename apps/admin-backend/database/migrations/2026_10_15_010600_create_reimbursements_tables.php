<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reimbursements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('category', 80); // Transportasi, Makan, Pulsa, Lainnya
            $table->bigInteger('amount'); // integer rupiah
            $table->date('expense_date');
            $table->string('description', 255)->nullable();
            $table->string('status', 20)->default('menunggu'); // menunggu, disetujui, ditolak, dibayar
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 255)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->timestamps();
            $table->index(['employee_id', 'status']);
        });

        Schema::create('reimbursement_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reimbursement_id')->constrained('reimbursements')->cascadeOnDelete();
            $table->foreignId('stored_file_id')->constrained('stored_files')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reimbursement_attachments');
        Schema::dropIfExists('reimbursements');
    }
};
