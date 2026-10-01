<?php

namespace App\Models;

use App\Domain\Geo\Enums\CityStatus;
use App\Models\Concerns\HasUppercaseUlids;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * country_id está denormalizado; la FK compuesta cities_region_country_fk garantiza
 * que coincide con el país de la región (DATABASE.md §7.3).
 */
#[Fillable(['country_id', 'region_id', 'name', 'slug', 'status'])]
class City extends Model
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
            'status' => CityStatus::class,
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

    public function aliases(): HasMany
    {
        return $this->hasMany(CityAlias::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }
}
