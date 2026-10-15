<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stored_files', function (Blueprint $table) {
            $table->id();
            $table->string('tdocs_file_id', 100)->nullable()->unique();
            $table->string('tdocs_folder_path', 255)->nullable();
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->bigInteger('size_bytes')->default(0);
            $table->string('folder', 40); // attendance, reimbursements, payslips, documents
            $table->string('status', 20)->default('pending'); // pending, uploaded, failed
            $table->integer('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['folder', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stored_files');
    }
};
