<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domain\Identity\Enums\UserStatus;
use App\Models\Concerns\HasUppercaseUlids;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasUppercaseUlids, Notifiable;

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * Refleja el DEFAULT de la columna para que un modelo recién creado no tenga status null.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => UserStatus::class,
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Por permiso, nunca por rol (MODELO-IDENTIDAD.md §5). Una cuenta suspendida
     * o inactiva no entra aunque conserve el rol.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->status === UserStatus::Active
            && $this->can('backoffice.access');
    }

    /**
     * La ficha que gestiona tras un claim aprobado; como máximo una (doctors_user_uniq).
     */
    public function doctor(): HasOne
    {
        return $this->hasOne(Doctor::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(DoctorClaim::class);
    }

    /**
     * El email se almacena en minúsculas (DATABASE.md §6.2); users_email_uniq es sobre lower(email).
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => mb_strtolower($value),
        );
    }
}
