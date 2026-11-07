<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaternalRecord extends Model
{
    protected function casts(): array
    {
        return [
            'record_date' => 'date',
            'patient_snapshot' => 'array',
            'details' => 'array',
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
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(MaternalVisit::class);
    }
}