<?php

namespace App\Models;

use App\Domain\Directory\Enums\FacilitySector;
use App\Domain\Directory\Enums\FacilityStatus;
use App\Domain\Directory\Enums\FacilityType;
use App\Models\Concerns\HasUppercaseUlids;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\Models\Activity;

/**
 * Hospital, clínica, centro médico o centro de salud (DATABASE.md §9.10). Es dueño de
 * sus sedes (locations.facility_id); sus médicos se derivan de ellas, no se guardan aparte.
 */
#[Fillable([
    'country_id',
    'network_id',
    'name',
    'slug',
    'type',
    'sector',
    'description',
    'logo_path',
    'status',
    'created_by_user_id',
])]
class Facility extends Model
{
    use HasUppercaseUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'type' => FacilityType::class,
            'sector' => FacilitySector::class,
            'status' => FacilityStatus::class,
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function network(): BelongsTo
    {
        return $this->belongsTo(FacilityNetwork::class, 'network_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Sus sedes.
     */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(FacilityContact::class);
    }

    /**
     * Convenios del establecimiento, independientes de las aseguradoras de sus médicos.
     */
    public function insurers(): BelongsToMany
    {
        return $this->belongsToMany(Insurer::class, 'facility_insurers')
            ->withPivot('created_at');
    }

    /**
     * Su historial en activity_log: las Actions registran cada cambio con el
     * establecimiento como sujeto (facility.*, slug.updated).
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    /**
     * Médicos que atienden en alguna de sus sedes (DATABASE.md §9.11). Es una consulta,
     * no una relación: la derivación pasa por doctor_locations y locations.
     *
     * @return Builder<Doctor>
     */
    public function doctors(): Builder
    {
        return Doctor::query()->whereHas('locations', fn (Builder $q) => $q->where('facility_id', $this->getKey()));
    }
}
