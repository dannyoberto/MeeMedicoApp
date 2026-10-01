<?php

namespace App\Models;

use App\Domain\Directory\Enums\SpecialtyAliasKind;
use App\Models\Concerns\HasUppercaseUlids;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['specialty_id', 'alias', 'alias_normalized', 'kind'])]
class SpecialtyAlias extends Model
{
    use HasUppercaseUlids;

    const UPDATED_AT = null;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'kind' => 'import',
    ];

    protected function casts(): array
    {
        return [
            'kind' => SpecialtyAliasKind::class,
        ];
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }
}
