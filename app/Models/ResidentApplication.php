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

    protected $hidden = [
        'id_document_path',
        'id_back_path',
        'selfie_path',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'identity_submitted_at' => 'datetime',
            'identity_reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasIdentityEvidence(): bool
    {
        return filled($this->id_document_path)
            && filled($this->id_back_path)
            && filled($this->selfie_path)
            && filled($this->identity_evidence_version)
            && $this->identity_submitted_at !== null;
    }
}