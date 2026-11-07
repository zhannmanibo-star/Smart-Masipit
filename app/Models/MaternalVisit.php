<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaternalVisit extends Model
{
    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'details' => 'array',
        ];
    }

    public function maternalRecord(): BelongsTo
    {
        return $this->belongsTo(MaternalRecord::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}