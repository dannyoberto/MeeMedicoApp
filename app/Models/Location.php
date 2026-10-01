<?php

namespace App\Models;

use App\Domain\Directory\Enums\LocationStatus;
use App\Models\Concerns\HasUppercaseUlids;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Puede estar compartida por varios médicos (DATABASE.md §9.6): si tiene más de uno,
 * solo la edita un admin. Esa regla es una Policy.
 */
#[Fillable([
    'country_id',
    'region_id',
    'city_id',
    'name',
    'address',
    'address_2',
    'postal_code',
    'address_normalized',
    'latitude',
    'longitude',
    'status',
    'created_by_user_id',
])]
class Location extends Model
{
    use HasUppercaseUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'status' => LocationStatus::class,
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function doctors(): BelongsToMany
    {
        return $this->belongsToMany(Doctor::class, 'doctor_locations')
            ->withPivot('location_type', 'is_primary', 'created_at');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(DoctorContact::class);
    }
}
