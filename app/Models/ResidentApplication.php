<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResidentApplication extends Model
{
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
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}