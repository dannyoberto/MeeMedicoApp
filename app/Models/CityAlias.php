<?php

namespace App\Models;

use App\Domain\Geo\Enums\CityAliasSource;
use App\Models\Concerns\HasUppercaseUlids;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['city_id', 'country_id', 'alias', 'alias_normalized', 'source'])]
class CityAlias extends Model
{
    use HasUppercaseUlids;

    const UPDATED_AT = null;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'source' => 'import',
    ];

    protected function casts(): array
    {
        return [
            'source' => CityAliasSource::class,
        ];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
