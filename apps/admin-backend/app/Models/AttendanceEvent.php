<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class AttendanceEvent extends Model
{
    public const TYPE_MASUK = 'MASUK';
    public const TYPE_KELUAR = 'KELUAR';

    protected $fillable = [
        'attendance_session_id', 'employee_id', 'event_type', 'server_at',
        'client_captured_at', 'latitude', 'longitude', 'accuracy_meters', 'address',
        'device_info', 'ip_address', 'idempotency_key', 'verification_status',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'server_at' => 'datetime',
            'client_captured_at' => 'datetime',
            'device_info' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(AttendanceEvidence::class);
    }

    /**
     * Attendance events are immutable: core fields may never change after creation.
     * Only verification_status / rejection_reason can be adjusted via the review flow.
     */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            $immutable = [
                'attendance_session_id', 'employee_id', 'event_type', 'server_at',
                'client_captured_at', 'latitude', 'longitude', 'accuracy_meters',
                'address', 'device_info', 'ip_address', 'idempotency_key',
            ];
            foreach ($immutable as $attr) {
                if ($this->isDirty($attr)) {
                    throw new LogicException("AttendanceEvent#{$this->id}: field [$attr] is immutable and cannot be modified.");
                }
            }
        }
        return parent::save($options);
    }
}
