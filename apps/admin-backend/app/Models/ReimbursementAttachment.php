<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReimbursementAttachment extends Model
{
    protected $fillable = ['reimbursement_id', 'stored_file_id'];

    public function reimbursement(): BelongsTo
    {
        return $this->belongsTo(Reimbursement::class);
    }

    public function storedFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class);
    }
}
