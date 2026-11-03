<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HealthImmunization extends Model
{
    protected function casts(): array
    {
        return [
            'administered_on' => 'date',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(
            HealthPatient::class,
            'health_patient_id'
        );
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'recorded_by'
        );
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(
            HealthImmunizationCorrection::class
        );
    }
}