<?php

namespace App\Models;

use App\Domain\Directory\Enums\ClaimStatus;
use App\Domain\Directory\Enums\DoctorGender;
use App\Domain\Directory\Enums\DoctorSource;
use App\Domain\Directory\Enums\DoctorStatus;
use App\Domain\Directory\Enums\LicenseSource;
use App\Domain\Directory\Enums\VerificationSource;
use App\Domain\Directory\Enums\VerificationStatus;
use App\Models\Concerns\HasUppercaseUlids;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Ficha del directorio (DATABASE.md §9.1). Existe sin User.
 *
 * Fuera de Fillable a propósito: slug, name_normalized, status, published_at,
 * verification_*, claim_status, claimed_at, user_id, merged_into_doctor_id y
 * license_verified_at. Solo los escriben las Actions (CreateDoctorAction,
 * PublishDoctorAction, VerifyDoctorAction, ApproveClaimAction, MergeDoctorsAction,
 * UpdateSlugAction), que imponen las invariantes que el motor no puede.
 *
 * search_vector es una columna generada: nunca se escribe.
 */
#[Fillable([
    'country_id',
    'created_by_user_id',
    'first_name',
    'last_name',
    'professional_name',
    'gender',
    'license_number',
    'license_source',
    'source',
    'import_batch_id',
])]
#[Hidden(['search_vector'])]
class Doctor extends Model
{
    use HasUppercaseUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'verification_status' => 'unverified',
        'claim_status' => 'unclaimed',
        'source' => 'admin',
    ];

    protected function casts(): array
    {
        return [
            'gender' => DoctorGender::class,
            'license_source' => LicenseSource::class,
            'license_verified_at' => 'datetime',
            'status' => DoctorStatus::class,
            'published_at' => 'datetime',
            'verification_status' => VerificationStatus::class,
            'verification_source' => VerificationSource::class,
            'verified_at' => 'datetime',
            'claim_status' => ClaimStatus::class,
            'claimed_at' => 'datetime',
            'source' => DoctorSource::class,
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Quien gestiona la ficha tras un claim aprobado. No es el creador.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_doctor_id');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(DoctorProfile::class);
    }

    public function specialties(): BelongsToMany
    {
        return $this->belongsToMany(Specialty::class, 'doctor_specialties')
            ->withPivot('is_primary', 'created_at');
    }

    public function languages(): BelongsToMany
    {
        return $this->belongsToMany(Language::class, 'doctor_languages')
            ->withPivot('created_at');
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'doctor_locations')
            ->withPivot('location_type', 'is_primary', 'created_at');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(DoctorContact::class);
    }

    public function externalReferences(): HasMany
    {
        return $this->hasMany(DoctorExternalReference::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(DoctorClaim::class);
    }

    public function contactEvents(): HasMany
    {
        return $this->hasMany(DoctorContactEvent::class);
    }
}
