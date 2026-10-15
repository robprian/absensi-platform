<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkAssignment extends Model
{
    protected $fillable = ['employee_id', 'work_site_id', 'assigned_at', 'unassigned_at', 'notes'];

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime', 'unassigned_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function workSite(): BelongsTo
    {
        return $this->belongsTo(WorkSite::class);
    }
}
