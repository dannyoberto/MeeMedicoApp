<?php

namespace App\Models;

use App\Domain\Directory\Enums\ContactType;
use App\Domain\Directory\Enums\FacilityContactSource;
use App\Models\Concerns\HasUppercaseUlids;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mismo patrón que DoctorContact (DATABASE.md §9.12). Si tiene location_id, es una sede
 * del propio establecimiento: lo garantiza FacilityContactsAction, no el motor.
 */
#[Fillable([
    'facility_id',
    'location_id',
    'type',
    'value',
    'value_normalized',
    'label',
    'is_public',
    'is_primary',
    'source',
])]
class FacilityContact extends Model
{
    use HasUppercaseUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_public' => true,
        'is_primary' => false,
        'source' => 'admin',
    ];

    protected function casts(): array
    {
        return [
            'type' => ContactType::class,
            'is_public' => 'boolean',
            'is_primary' => 'boolean',
            'source' => FacilityContactSource::class,
        ];
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
