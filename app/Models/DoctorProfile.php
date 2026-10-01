<?php

namespace App\Models;

use App\Models\Concerns\HasUppercaseUlids;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Se crea siempre en la misma transacción que el Doctor (CreateDoctorAction, DATABASE.md §9.2).
 */
#[Fillable(['doctor_id', 'headline', 'bio', 'education', 'experience', 'profile_photo_path'])]
class DoctorProfile extends Model
{
    use HasUppercaseUlids;

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }
}
