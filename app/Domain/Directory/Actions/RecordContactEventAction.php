<?php

namespace App\Domain\Directory\Actions;

use App\Domain\Directory\Enums\ContactEventSource;
use App\Domain\Directory\Enums\ContactEventType;
use App\Domain\Directory\Enums\DoctorStatus;
use App\Domain\Directory\Exceptions\DirectoryRuleException;
use App\Models\Doctor;
use App\Models\DoctorContactEvent;
use Illuminate\Support\Carbon;

/**
 * Registra que un paciente pulsó un contacto de la ficha o del listado (DATABASE.md
 * §14.3). Es la base de "te contactaron N veces este mes", que no se puede reconstruir
 * hacia atrás.
 *
 * Sin PII: del visitante solo queda un hash con sal que cambia cada día. Sirve para
 * contar visitantes distintos en un día, no para seguir a nadie entre días. No se
 * audita en activity_log: es append-only y de alto volumen.
 */
class RecordContactEventAction
{
    /**
     * @param  string|null  $fingerprint  datos del visitante sin hashear (IP + user agent);
     *                                    nunca se guardan tal cual
     */
    public function execute(
        Doctor $doctor,
        ContactEventType $type,
        ContactEventSource $source,
        ?string $fingerprint,
    ): DoctorContactEvent {
        if ($doctor->status !== DoctorStatus::Active) {
            throw new DirectoryRuleException('Solo se registran contactos de fichas publicadas.');
        }

        $now = Carbon::now();

        return DoctorContactEvent::create([
            'doctor_id' => $doctor->id,
            'country_id' => $doctor->country_id,
            'contact_type' => $type,
            'source' => $source,
            'session_hash' => $fingerprint === null ? null : self::hash($fingerprint, $now),
            'occurred_at' => $now,
        ]);
    }

    /**
     * sha256 (64 caracteres, como la columna) con una sal derivada de APP_KEY y del día.
     */
    public static function hash(string $fingerprint, Carbon $at): string
    {
        $salt = hash_hmac('sha256', $at->toDateString(), (string) config('app.key'));

        return hash_hmac('sha256', $fingerprint, $salt);
    }
}
