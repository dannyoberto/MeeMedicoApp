<?php

namespace App\Models;

use App\Domain\Directory\Enums\InsurerStatus;
use App\Domain\Directory\Enums\InsurerType;
use App\Models\Concerns\HasUppercaseUlids;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Aseguradora de un país, sin planes (DATABASE.md §9.13).
 */
#[Fillable(['country_id', 'name', 'slug', 'type', 'status'])]
class Insurer extends Model
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
            'type' => InsurerType::class,
            'status' => InsurerStatus::class,
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function doctors(): BelongsToMany
    {
        return $this->belongsToMany(Doctor::class, 'doctor_insurers')
            ->withPivot('created_at');
    }

    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class, 'facility_insurers')
            ->withPivot('created_at');
    }
}
