<?php

namespace App\Models;

use App\Domain\Directory\Enums\SlugRedirectEntity;
use App\Models\Concerns\HasUppercaseUlids;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Slug antiguo → entidad, para responder 301 (DATABASE.md §14.2). Lo escribe UpdateSlugAction.
 *
 * entity_id no tiene FK: apunta a doctors, specialties, cities o regions según entity_type.
 */
#[Fillable(['entity_type', 'entity_id', 'country_id', 'old_slug'])]
class SlugRedirect extends Model
{
    use HasUppercaseUlids;

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'entity_type' => SlugRedirectEntity::class,
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
