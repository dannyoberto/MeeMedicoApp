<?php

namespace App\Models;

use App\Models\Concerns\HasUppercaseUlids;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Médicos que pidieron salir del directorio. El importador la consulta antes de crear
 * cualquier ficha (DATABASE.md §14.1). Mientras está vigente (revoked_at nulo) bloquea;
 * si la persona quiere volver, se revoca con RevokeSuppressionAction y el registro queda.
 */
#[Fillable([
    'country_id',
    'license_number',
    'email_normalized',
    'phone_normalized',
    'name_normalized',
    'reason',
    'requested_at',
    'created_by_user_id',
])]
class DoctorSuppression extends Model
{
    use HasUppercaseUlids;

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'revocation_requested_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }
}
