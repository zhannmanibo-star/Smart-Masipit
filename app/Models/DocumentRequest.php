<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRequest extends Model
{
    public const TYPES = [
        'barangay_clearance' => 'Barangay Clearance',
        'certificate_of_indigency' => 'Certificate of Indigency',
        'certificate_of_residency' => 'Certificate of Residency',
    ];

    public const STATUSES = [
        'submitted' => 'Submitted',
        'under_review' => 'Under review',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'ready_for_release' => 'Ready for release',
        'released' => 'Released',
        'closed_not_released' => 'Closed — Not released',
    ];

    protected function casts(): array
    {
        return [
            'resident_snapshot' => 'array',
            'approved_snapshot' => 'array',
            'issuance_date' => 'date',
            'prepared_at' => 'datetime',
            'ready_at' => 'datetime',
            'released_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}