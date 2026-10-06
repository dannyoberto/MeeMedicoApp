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
 * Puede estar compartida por varios médicos (DATABASE.md §9.6) y pertenecer a un
 * establecimiento como sede (§9.11). En ambos casos solo la edita un admin: es una Policy.
 */
#[Fillable([
    'facility_id',
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

    /**
     * El establecimiento del que es sede, si lo hay.
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
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
