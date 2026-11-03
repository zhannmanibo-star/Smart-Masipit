<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthImmunizationCorrection extends Model
{
    public function immunization(): BelongsTo
    {
        return $this->belongsTo(
            HealthImmunization::class,
            'health_immunization_id'
        );
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'recorded_by'
        );
    }
}