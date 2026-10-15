<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    protected $fillable = ['payroll_item_id', 'reference_code', 'pdf_file_id', 'published', 'published_at'];

    protected function casts(): array
    {
        return ['published' => 'boolean', 'published_at' => 'datetime'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(PayrollItem::class, 'payroll_item_id');
    }

    public function pdfFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'pdf_file_id');
    }
}
