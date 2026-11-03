<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HouseholdMember extends Model
{
    public const RELATIONSHIPS = [
        'head' => 'Head of household',
        'spouse' => 'Spouse',
        'partner' => 'Partner',
        'child' => 'Child',
        'parent' => 'Parent',
        'sibling' => 'Sibling',
        'grandchild' => 'Grandchild',
        'grandparent' => 'Grandparent',
        'other_relative' => 'Other relative',
        'non_relative' => 'Non-relative',
    ];

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class)->withTrashed();
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}