<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoredFile extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_UPLOADED = 'uploaded';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'tdocs_file_id', 'tdocs_folder_path', 'staged_path', 'original_name', 'mime_type',
        'size_bytes', 'folder', 'status', 'attempts', 'last_error', 'uploaded_by',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isUploaded(): bool
    {
        return $this->status === self::STATUS_UPLOADED && $this->tdocs_file_id !== null;
    }
}
