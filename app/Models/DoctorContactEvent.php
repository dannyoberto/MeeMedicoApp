<?php

namespace App\Models;

use App\Domain\Directory\Enums\ContactEventSource;
use App\Domain\Directory\Enums\ContactEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only y de alto volumen: PK bigint de identidad, sin ULID ni timestamps (DATABASE.md §14.3).
 */
#[Fillable(['doctor_id', 'country_id', 'contact_type', 'source', 'session_hash', 'occurred_at'])]
#[WithoutTimestamps]
class DoctorContactEvent extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'source' => 'profile',
    ];

    protected function casts(): array
    {
        return [
            'contact_type' => ContactEventType::class,
            'source' => ContactEventSource::class,
            'occurred_at' => 'datetime',
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
