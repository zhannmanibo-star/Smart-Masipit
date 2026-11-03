<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function residentApplication(): HasOne
    {
        return $this->hasOne(ResidentApplication::class);
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function canAccessServices(): bool
    {
        if ($this->account_status !== 'approved') {
            return false;
        }

        if ($this->role === 'resident') {
            return $this->resident_id !== null
                && $this->verified_by !== null
                && $this->verified_at !== null
                && $this->resident()->exists();
        }

        return in_array($this->role, [
            'admin',
            'secretary',
            'captain',
            'barangay_staff',
            'approving_officer',
            'health_staff',
        ], true);
    }

    public function canAccessHealthRecords(): bool
    {
        return $this->canAccessServices()
            && $this->role === 'health_staff';
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}