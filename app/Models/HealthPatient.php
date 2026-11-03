<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HealthPatient extends Model
{
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class)
            ->withTrashed();
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'registered_by'
        );
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(
            HealthConsultation::class,
            'health_patient_id'
        );
    }
}