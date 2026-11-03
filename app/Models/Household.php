<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Household extends Model
{
    public function head(): BelongsTo
    {
        return $this->belongsTo(
            Resident::class,
            'head_resident_id'
        )->withTrashed();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(HouseholdMember::class);
    }

    public function activeMemberships(): HasMany
    {
        return $this->hasMany(HouseholdMember::class)
            ->whereHas('resident', function ($query) {
                $query->whereNull('residents.deleted_at')
                    ->whereNull('residents.date_of_death');
            });
    }

    public function needsHeadReplacement(): bool
    {
        $head = $this->head;

        return $head === null
            || $head->trashed()
            || $head->isDeceased();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}