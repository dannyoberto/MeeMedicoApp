<?php

namespace App\Models;

use App\Models\Concerns\HasUppercaseUlids;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Al fusionar, el superviviente hereda las referencias de ambos (DATABASE.md §9.8).
 */
#[Fillable(['doctor_id', 'source', 'reference', 'first_seen_at', 'last_seen_at'])]
class DoctorExternalReference extends Model
{
    use HasUppercaseUlids;

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }
}
