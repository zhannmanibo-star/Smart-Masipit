<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Resident extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'birth_date',
        'sex',
        'sitio',
        'address',
        'contact_number',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'date_of_death' => 'date',
        ];
    }

    public function isDeceased(): bool
    {
        return $this->date_of_death !== null;
    }

    public function scopeWithoutRecordedDeath(Builder $query): Builder
    {
        return $query->whereNull('date_of_death');
    }

    public function scopeDeceased(Builder $query): Builder
    {
        return $query->whereNotNull('date_of_death');
    }

    public function householdMembership(): HasOne
    {
        return $this->hasOne(HouseholdMember::class);
    }

    public function headedHousehold(): HasOne
    {
        return $this->hasOne(
            Household::class,
            'head_resident_id'
        );
    }
}