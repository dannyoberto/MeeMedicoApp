<?php

use App\Domain\Identity\Actions\CreateAdminUserAction;
use App\Domain\Identity\Actions\InviteUserAction;
use App\Domain\Identity\Actions\ReactivateUserAction;
use App\Domain\Identity\Actions\SuspendUserAction;
use App\Domain\Identity\Actions\SyncUserRolesAction;
use App\Domain\Identity\Exceptions\IdentityRuleException;
use App\Models\User;
use App\Notifications\PasswordSetupNotification;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

describe('alta', function () {
    it('el admin de consola se crea con correo en minúsculas, verificado y con rol auditado', function () {
        $admin = app(CreateAdminUserAction::class)->execute('Admin', 'Admin@MeeMedico.com', 'una-clave-larga-123');

        expect($admin->email)->toBe('admin@meemedico.com')
            ->and($admin->email_verified_at)->not->toBeNull()
            ->and($admin->can('backoffice.access'))->toBeTrue()
            ->and(Activity::where('subject_id', $admin->id)->where('event', 'role.assigned')->exists())->toBeTrue();
    });

    it('el correo es único sin importar mayúsculas', function () {
        admin('ana@x.com');

        expect(fn () => app(InviteUserAction::class)->execute('Ana', 'ANA@x.com', ['admin'], null))->toThrow(ValidationException::class);
    });

    it('la invitación crea una cuenta cerrada hasta que se usa el enlace', function () {
        Notification::fake();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $user = app(InviteUserAction::class)->execute('Operadora', 'op@x.com', ['admin'], null);

        expect(Auth::validate(['email' => 'op@x.com', 'password' => 'una-clave-larga-123']))->toBeFalse()
            ->and($user->email_verified_at)->toBeNull();

        $url = null;
        Notification::assertSentTo($user, PasswordSetupNotification::class, function ($n) use (&$url) {
            $url = $n->url;

            return true;
        });
        expect($url)->toContain('admin.localhost')->toContain('password-reset/reset');

        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $status = Password::broker()->reset(
            ['email' => 'op@x.com', 'token' => $query['token'], 'password' => 'nueva-clave-456', 'password_confirmation' => 'nueva-clave-456'],
            function (User $u, string $password) {
                $u->forceFill(['password' => $password])->save();
                event(new PasswordReset($u));
            },
        );

        expect($status)->toBe(Password::PASSWORD_RESET)
            ->and($user->fresh()->email_verified_at)->not->toBeNull()
            ->and(Auth::attempt(['email' => 'op@x.com', 'password' => 'nueva-clave-456']))->toBeTrue()
            ->and($user->fresh()->last_login_at)->not->toBeNull();
    });
});

describe('roles', function () {
    it('asignar y retirar queda auditado', function () {
        $admin = admin();
        $user = doctorUser();
        $sync = app(SyncUserRolesAction::class);

        $sync->execute($user, ['doctor', 'admin'], $admin);
        $sync->execute($user, ['doctor'], $admin);

        expect(Activity::where('subject_id', $user->id)->pluck('event')->all())->toContain('role.assigned', 'role.removed');
    });

    it('nadie se quita a sí mismo el acceso al backoffice', function () {
        $admin = admin();
        admin('otro@x.com');

        expect(fn () => app(SyncUserRolesAction::class)->execute($admin, ['doctor'], $admin))->toThrow(IdentityRuleException::class);
    });

    it('siempre queda al menos un usuario activo con acceso', function () {
        $actor = admin();
        $last = admin('ultimo@x.com');
        $actor->forceFill(['status' => 'suspended'])->save();

        expect(fn () => app(SyncUserRolesAction::class)->execute($last, [], null))->toThrow(IdentityRuleException::class)
            ->and(fn () => app(SuspendUserAction::class)->execute($last, null, 'x'))->toThrow(IdentityRuleException::class);
    });
});

describe('suspensión', function () {
    it('surte efecto de inmediato: sesiones, tokens y "recordarme"', function () {
        $admin = admin();
        $target = admin('b@x.com');
        $target->forceFill(['remember_token' => 'token-viejo'])->save();
        DB::table('sessions')->insert(['id' => 's1', 'user_id' => $target->id, 'payload' => 'x', 'last_activity' => time()]);
        $target->createToken('api');

        app(SuspendUserAction::class)->execute($target, $admin, 'Salió del equipo');
        $target->refresh();

        expect($target->status->value)->toBe('suspended')
            ->and(DB::table('sessions')->where('user_id', $target->id)->exists())->toBeFalse()
            ->and($target->tokens()->count())->toBe(0)
            ->and($target->remember_token)->not->toBe('token-viejo')
            ->and($target->canAccessPanel(Filament::getPanel('admin')))->toBeFalse()
            ->and(Activity::where('subject_id', $target->id)->where('event', 'user.suspended')->first()->properties['reason'])->toBe('Salió del equipo');
    });

    it('nadie se suspende a sí mismo', function () {
        $admin = admin();
        admin('otro@x.com');

        expect(fn () => app(SuspendUserAction::class)->execute($admin, $admin, 'x'))->toThrow(IdentityRuleException::class);
    });

    it('reactivar devuelve el acceso', function () {
        $admin = admin();
        $target = admin('b@x.com');
        app(SuspendUserAction::class)->execute($target, $admin, 'x');
        app(ReactivateUserAction::class)->execute($target, $admin);

        expect($target->fresh()->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
    });
});

it('el acceso al panel es por permiso: un médico no entra', function () {
    expect(doctorUser()->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
});
