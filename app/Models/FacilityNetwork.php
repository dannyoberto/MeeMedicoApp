<?php

namespace App\Models;

use App\Domain\Directory\Enums\FacilityNetworkStatus;
use App\Domain\Directory\Enums\FacilitySector;
use App\Models\Concerns\HasUppercaseUlids;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Red operadora de establecimientos: CCSS, IGSS, SNS, IVSS o un grupo privado (DATABASE.md §9.9).
 */
#[Fillable(['country_id', 'name', 'short_name', 'sector', 'status'])]
class FacilityNetwork extends Model
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
            'sector' => FacilitySector::class,
            'status' => FacilityNetworkStatus::class,
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function facilities(): HasMany
    {
        return $this->hasMany(Facility::class, 'network_id');
    }
}
