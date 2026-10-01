<?php

namespace App\Models;

use App\Domain\Import\Enums\ImportBatchStatus;
use App\Models\Concerns\HasUppercaseUlids;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'country_id',
    'created_by_user_id',
    'source',
    'file_name',
    'file_hash',
    'file_path',
    'status',
    'rows_total',
    'rows_new',
    'rows_matched',
    'rows_review',
    'rows_applied',
    'rows_skipped',
    'rows_failed',
    'started_at',
    'finished_at',
    'notes',
])]
class ImportBatch extends Model
{
    use HasUppercaseUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'ingesting',
    ];

    protected function casts(): array
    {
        return [
            'status' => ImportBatchStatus::class,
            'rows_total' => 'integer',
            'rows_new' => 'integer',
            'rows_matched' => 'integer',
            'rows_review' => 'integer',
            'rows_applied' => 'integer',
            'rows_skipped' => 'integer',
            'rows_failed' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class, 'batch_id');
    }

    public function doctors(): HasMany
    {
        return $this->hasMany(Doctor::class);
    }
}
