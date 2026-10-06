<?php

namespace App\Models;

use App\Domain\Geo\Enums\CountryStatus;
use App\Models\Concerns\HasUppercaseUlids;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'dial_code', 'slug', 'status'])]
class Country extends Model
{
    use HasUppercaseUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'inactive',
    ];

    protected function casts(): array
    {
        return [
            'status' => CountryStatus::class,
        ];
    }

    public function regions(): HasMany
    {
        return $this->hasMany(Region::class);
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    public function doctors(): HasMany
    {
        return $this->hasMany(Doctor::class);
    }

    public function facilities(): HasMany
    {
        return $this->hasMany(Facility::class);
    }

    public function insurers(): HasMany
    {
        return $this->hasMany(Insurer::class);
    }
}
