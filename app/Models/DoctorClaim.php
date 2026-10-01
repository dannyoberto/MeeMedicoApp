<?php

namespace App\Models;

use App\Domain\Claim\Enums\DoctorClaimStatus;
use App\Models\Concerns\HasUppercaseUlids;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fuente de verdad del claim; doctors.claim_status es una copia cacheada (DATABASE.md §10.1).
 *
 * status, reviewed_by_user_id, reviewed_at y resolution_reason quedan fuera de
 * Fillable: solo los escriben ApproveClaimAction y RejectClaimAction.
 */
#[Fillable([
    'doctor_id',
    'user_id',
    'claimed_license_number',
    'contact_email',
    'contact_phone',
    'evidence_path',
    'applicant_notes',
])]
class DoctorClaim extends Model
{
    use HasUppercaseUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'status' => DoctorClaimStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
