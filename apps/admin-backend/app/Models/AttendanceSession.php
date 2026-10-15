<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSession extends Model
{
    protected $fillable = ['employee_id', 'work_date', 'work_site_id', 'status', 'total_minutes'];

    protected function casts(): array
    {
        return ['work_date' => 'date'];
    }

    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_EXCEPTION = 'exception';

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function workSite(): BelongsTo
    {
        return $this->belongsTo(WorkSite::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AttendanceEvent::class)->orderBy('server_at');
    }

    public function clockIn(): ?AttendanceEvent
    {
        return $this->events()->where('event_type', AttendanceEvent::TYPE_MASUK)->first();
    }

    public function clockOut(): ?AttendanceEvent
    {
        return $this->events()->where('event_type', AttendanceEvent::TYPE_KELUAR)->first();
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(AttendanceCorrection::class);
    }

    public function breakRecords(): HasMany
    {
        return $this->hasMany(BreakRecord::class);
    }
}
