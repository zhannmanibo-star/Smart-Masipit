<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthHistoryEntry extends Model
{
    public const CATEGORIES = [
        'allergies' => 'Allergies and reactions',
        'conditions' => 'Existing conditions',
        'past_history' => 'Past illnesses, operations, and hospitalizations',
    ];

    public const STATUSES = [
        'reported' => 'Information reported or documented',
        'none_reported' => 'None reported by the source',
        'unknown' => 'Unknown / source cannot confirm',
        'not_assessed' => 'Not yet assessed',
    ];

    public const SOURCES = [
        'patient' => 'Patient interview',
        'representative' => 'Parent, guardian, or representative',
        'document' => 'Existing health document',
        'health_personnel' => 'Responsible health personnel',
    ];

    protected function casts(): array
    {
        return [
            'source_date' => 'date',
            'version' => 'integer',
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
}