<?php

use App\Domain\Directory\Actions\CreateSuppressionAction;
use App\Domain\Directory\Actions\DoctorContactsAction;
use App\Domain\Directory\Actions\PublishDoctorAction;
use App\Domain\Directory\Actions\RevokeSuppressionAction;
use App\Domain\Directory\Actions\UnpublishDoctorAction;
use App\Domain\Directory\Actions\UpdateDoctorAction;
use App\Domain\Directory\Cdn\PurgeCdnPaths;
use App\Domain\Directory\Exceptions\DirectoryRuleException;
use App\Domain\Directory\Support\SuppressionCheck;
use App\Models\DoctorSuppression;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

/*
| La supresión vigente bloquea por cualquier camino (DATABASE.md §14.1, §11):
| al crear, al añadir claves fuertes y, como última puerta, al publicar.
| Revocada, deja de bloquear y el registro queda.
*/

beforeEach(fn () => Bus::fake([PurgeCdnPaths::class]));

function suppress(?string $license = null, ?string $email = null, ?string $phone = null, ?string $name = null): DoctorSuppression
{
    return app(CreateSuppressionAction::class)->execute(country(), $license, $email, $phone, $name, 'Correo', now()->subDay(), null);
}

function revoke(DoctorSuppression $suppression, ?string $reason = 'WhatsApp; confirmó por llamada al número de la ficha', ?CarbonInterface $at = null): DoctorSuppression
{
    return app(RevokeSuppressionAction::class)->execute($suppression, $reason, $at ?? now(), null);
}

describe('publicación', function () {
    it('no republica una ficha despublicada por supresión', function () {
        $doctor = publishedDoctor();
        suppress(phone: '2222-3333');
        app(UnpublishDoctorAction::class)->execute($doctor, null);

        expect(fn () => app(PublishDoctorAction::class)->execute($doctor->fresh(), null))
            ->toThrow(DirectoryRuleException::class, 'pidió no aparecer');
    });

    it('bloquea por colegiado y por correo de la ficha', function (Closure $setup) {
        $doctor = publishableDoctor(['license_number' => 'MED-9']);
        app(DoctorContactsAction::class)->save($doctor, null, ['type' => 'email', 'value' => 'ana@clinica.com'], null);
        $setup();

        expect(fn () => app(PublishDoctorAction::class)->execute($doctor->fresh(), null))->toThrow(DirectoryRuleException::class);
    })->with([
        'colegiado' => [fn () => suppress(license: 'MED-9')],
        'correo' => [fn () => suppress(email: 'Ana@Clinica.com')],
    ]);

    it('una supresión solo por nombre no bloquea: el homónimo se confirmó al crear', function () {
        $doctor = publishableDoctor();
        suppress(name: 'Ana Rojas');

        app(PublishDoctorAction::class)->execute($doctor, null);

        expect($doctor->fresh()->status->value)->toBe('active');
    });

    it('una supresión de otro país no bloquea', function () {
        $doctor = publishableDoctor(['license_number' => 'MED-9']);
        app(CreateSuppressionAction::class)->execute(country('GT'), 'MED-9', null, null, null, 'x', now(), null);

        app(PublishDoctorAction::class)->execute($doctor, null);

        expect($doctor->fresh()->status->value)->toBe('active');
    });
});

describe('claves fuertes añadidas después', function () {
    it('no deja añadir el teléfono o el correo de una persona suprimida', function (string $type, string $value) {
        suppress(email: 'ana@clinica.com', phone: '8888-1234');

        expect(fn () => app(DoctorContactsAction::class)->save(makeDoctor(), null, ['type' => $type, 'value' => $value], null))
            ->toThrow(ValidationException::class, 'pidió no aparecer');
    })->with([
        'whatsapp' => ['whatsapp', '8888 1234'],
        'correo' => ['email', 'ANA@clinica.com'],
    ]);

    it('deja editar un contacto que ya estaba si no cambia su valor', function () {
        $doctor = publishableDoctor();
        suppress(phone: '2222-3333');
        $contact = $doctor->contacts()->firstOrFail();

        app(DoctorContactsAction::class)->save($doctor, $contact, ['type' => 'phone', 'value' => '2222-3333', 'label' => 'Consultorio'], null);

        expect($contact->fresh()->label)->toBe('Consultorio');
    });

    it('no deja cambiar el colegiado a uno suprimido', function () {
        suppress(license: 'MED-5');

        expect(fn () => app(UpdateDoctorAction::class)->execute(makeDoctor(), ['license_number' => 'MED-5'], null))
            ->toThrow(ValidationException::class, 'pidió no aparecer');
    });
});

describe('revocación', function () {
    it('registra quién, cuándo y por qué, y lo audita', function () {
        $actor = admin();
        $suppression = app(RevokeSuppressionAction::class)->execute(suppress(license: 'MED-5'), ' WhatsApp ', now(), $actor);

        expect($suppression->fresh())
            ->revocation_reason->toBe('WhatsApp')
            ->revoked_by_user_id->toBe($actor->id)
            ->revoked_at->not->toBeNull()
            ->isRevoked()->toBeTrue()
            ->and(Activity::where('subject_id', $suppression->id)->where('event', 'suppression.revoked')->exists())->toBeTrue();
    });

    it('se revoca una sola vez', function () {
        $suppression = revoke(suppress(license: 'MED-5'));

        expect(fn () => revoke($suppression))->toThrow(DirectoryRuleException::class);
    });

    it('exige motivo y una fecha posible', function (?string $reason, Closure $at) {
        expect(fn () => revoke(suppress(license: 'MED-5'), $reason, $at()))->toThrow(ValidationException::class);
    })->with([
        'sin motivo' => [' ', fn () => now()],
        'fecha futura' => ['x', fn () => now()->addDay()],
        'antes de la supresión' => ['x', fn () => now()->subWeek()],
    ]);

    it('deja de bloquear: la ficha se crea y se publica de nuevo', function () {
        $doctor = publishedDoctor(['license_number' => 'MED-9']);
        $suppression = suppress(license: 'MED-9', phone: '2222-3333');
        app(UnpublishDoctorAction::class)->execute($doctor, null);

        revoke($suppression);
        app(PublishDoctorAction::class)->execute($doctor->fresh(), null);

        expect($doctor->fresh()->status->value)->toBe('active')
            ->and(SuppressionCheck::strongMatch(country()->id, 'MED-9', ['+50622223333']))->toBeNull();
    });

    it('una revocada no impide crear la ficha por colegiado ni pide confirmar homónimo', function () {
        revoke(suppress(license: 'MED-5', name: 'Ana Rojas'));

        expect(makeDoctor(['license_number' => 'MED-5'])->exists)->toBeTrue();
    });
});
