<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkSchedule extends Model
{
    protected $fillable = [
        'employee_id', 'day_of_week', 'shift_name', 'start_time', 'end_time',
        'overnight', 'break_minutes', 'active',
    ];

    protected function casts(): array
    {
        return ['overnight' => 'boolean', 'active' => 'boolean', 'break_minutes' => 'integer'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
