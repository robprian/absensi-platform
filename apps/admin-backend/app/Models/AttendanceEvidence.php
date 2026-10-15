<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceEvidence extends Model
{
    public const TYPE_ORIGINAL = 'selfie_original';
    public const TYPE_WATERMARKED = 'selfie_watermarked';

    protected $fillable = ['attendance_event_id', 'evidence_type', 'stored_file_id', 'client_metadata'];

    protected function casts(): array
    {
        return ['client_metadata' => 'array'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(AttendanceEvent::class, 'attendance_event_id');
    }

    public function storedFile(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class);
    }
}
