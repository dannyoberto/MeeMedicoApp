<?php

use App\Domain\Directory\Actions\RecordContactEventAction;
use App\Domain\Directory\Enums\ContactEventSource;
use App\Domain\Directory\Enums\ContactEventType;
use App\Domain\Directory\Exceptions\DirectoryRuleException;
use App\Models\DoctorContactEvent;
use Illuminate\Support\Carbon;

/*
| DATABASE.md §14.3: cada contacto de un paciente se registra desde el primer día, sin
| PII. El visitante queda como un hash con sal diaria.
*/

it('registra el contacto con el país de la ficha y sin datos del visitante en claro', function () {
    $doctor = publishedDoctor();

    $event = app(RecordContactEventAction::class)->execute($doctor, ContactEventType::Whatsapp, ContactEventSource::Listing, '203.0.113.7|Mozilla');

    expect($event->country_id)->toBe($doctor->country_id)
        ->and($event->contact_type)->toBe(ContactEventType::Whatsapp)
        ->and($event->source)->toBe(ContactEventSource::Listing)
        ->and($event->session_hash)->toHaveLength(64)
        ->and($event->session_hash)->not->toContain('203.0.113.7');
});

it('no registra contactos de fichas que no están publicadas', function () {
    $doctor = publishableDoctor();

    expect(fn () => app(RecordContactEventAction::class)->execute($doctor, ContactEventType::Phone, ContactEventSource::Profile, null))
        ->toThrow(DirectoryRuleException::class);

    expect(DoctorContactEvent::count())->toBe(0);
});

it('cambia la sal cada día: no permite seguir a un visitante entre días', function () {
    $today = RecordContactEventAction::hash('ip|ua', Carbon::parse('2026-10-15 23:59'));

    expect(RecordContactEventAction::hash('ip|ua', Carbon::parse('2026-10-15 08:00')))->toBe($today)
        ->and(RecordContactEventAction::hash('ip|ua', Carbon::parse('2026-10-16 00:01')))->not->toBe($today);
});
