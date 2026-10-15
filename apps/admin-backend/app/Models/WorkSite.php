<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkSite extends Model
{
    protected $fillable = [
        'name', 'address', 'latitude', 'longitude', 'radius_meters', 'geofence_enabled',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'radius_meters' => 'integer',
            'geofence_enabled' => 'boolean',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(WorkAssignment::class);
    }
}
