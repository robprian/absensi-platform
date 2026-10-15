<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    protected $fillable = ['name', 'period_type', 'starts_on', 'ends_on', 'status'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function runs(): HasMany
    {
        return $this->hasMany(PayrollRun::class);
    }
}
