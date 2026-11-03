<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthConsultationAddendum extends Model
{
    protected $table = 'health_consultation_addendums';

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(
            HealthConsultation::class,
            'health_consultation_id'
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