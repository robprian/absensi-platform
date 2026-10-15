<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_REVIEW = 'review';
    public const STATUS_FINALIZED = 'finalized';
    public const STATUS_LOCKED = 'locked';

    protected $fillable = [
        'payroll_period_id', 'status', 'notes', 'finalized_at', 'finalized_by', 'rate_snapshot',
    ];

    protected function casts(): array
    {
        return ['finalized_at' => 'datetime', 'rate_snapshot' => 'array'];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REVIEW], true);
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }
}
