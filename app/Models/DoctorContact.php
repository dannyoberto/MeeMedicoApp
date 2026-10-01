<?php

namespace App\Models;

use App\Domain\Directory\Enums\ContactSource;
use App\Domain\Directory\Enums\ContactType;
use App\Models\Concerns\HasUppercaseUlids;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'doctor_id',
    'location_id',
    'type',
    'value',
    'value_normalized',
    'label',
    'is_public',
    'is_primary',
    'verified_at',
    'source',
])]
class DoctorContact extends Model
{
    use HasUppercaseUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_public' => true,
        'is_primary' => false,
        'source' => 'admin',
    ];

    protected function casts(): array
    {
        return [
            'type' => ContactType::class,
            'is_public' => 'boolean',
            'is_primary' => 'boolean',
            'verified_at' => 'datetime',
            'source' => ContactSource::class,
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
