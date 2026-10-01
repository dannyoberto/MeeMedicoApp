<?php

namespace App\Models;

use App\Domain\Import\Enums\ImportResolution;
use App\Domain\Import\Enums\ImportRowStatus;
use App\Domain\Import\Enums\MatchConfidence;
use App\Domain\Import\Enums\MatchType;
use App\Models\Concerns\HasUppercaseUlids;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Staging del importador: el pipeline nunca escribe en doctors directamente (DATABASE.md §12.2).
 */
#[Fillable([
    'batch_id',
    'row_number',
    'raw_payload',
    'row_hash',
    'n_first_name',
    'n_last_name',
    'n_name_key',
    'n_license',
    'n_phone_e164',
    'n_email',
    'n_country_id',
    'n_city_id',
    'n_specialty_ids',
    'n_address',
    'match_type',
    'match_confidence',
    'matched_doctor_id',
    'candidate_doctor_ids',
    'status',
    'validation_errors',
    'resolution',
    'resolved_by_user_id',
    'resolved_at',
    'applied_doctor_id',
    'applied_at',
])]
class ImportRow extends Model
{
    use HasUppercaseUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'match_confidence' => 'none',
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'row_number' => 'integer',
            'raw_payload' => 'array',
            'n_specialty_ids' => 'array',
            'match_type' => MatchType::class,
            'match_confidence' => MatchConfidence::class,
            'candidate_doctor_ids' => 'array',
            'status' => ImportRowStatus::class,
            'validation_errors' => 'array',
            'resolution' => ImportResolution::class,
            'resolved_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'batch_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'n_country_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'n_city_id');
    }

    public function matchedDoctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'matched_doctor_id');
    }

    public function appliedDoctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'applied_doctor_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }
}
