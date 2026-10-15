<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdempotencyRecord extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'key', 'user_id', 'method', 'route', 'request_hash',
        'response_status', 'response_body', 'expires_at',
    ];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
