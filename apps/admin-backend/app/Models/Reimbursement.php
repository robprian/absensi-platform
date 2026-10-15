<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reimbursement extends Model
{
    public const STATUS_MENUNGGU = 'menunggu';
    public const STATUS_DISETUJUI = 'disetujui';
    public const STATUS_DITOLAK = 'ditolak';
    public const STATUS_DIBAYAR = 'dibayar';

    protected $fillable = [
        'employee_id', 'category', 'amount', 'expense_date', 'description',
        'status', 'reviewed_by', 'reviewed_at', 'review_note', 'paid_at',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'expense_date' => 'date',
            'reviewed_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ReimbursementAttachment::class);
    }

    public function canTransitionTo(string $to): bool
    {
        return match ($this->status) {
            self::STATUS_MENUNGGU => in_array($to, [self::STATUS_DISETUJUI, self::STATUS_DITOLAK], true),
            self::STATUS_DISETUJUI => $to === self::STATUS_DIBAYAR,
            default => false,
        };
    }
}
