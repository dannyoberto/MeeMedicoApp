<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
| Invariantes que impone el motor (DATABASE.md). §19 exige probar cada índice único
| parcial intentando violarlo: aquí se hace con SQL directo, sin pasar por Actions,
| para demostrar que la base se defiende sola.
*/

function ulid(string $suffix): string
{
    return '01K'.str_repeat('0', 23 - strlen($suffix)).$suffix;
}

function violates(string $constraint, Closure $sql): void
{
    try {
        DB::transaction($sql);
    } catch (QueryException $e) {
        expect($e->getMessage())->toContain($constraint);

        return;
    }

    test()->fail("Se esperaba una violación de {$constraint}");
}

beforeEach(function () {
    $this->cr = country('CR')->id;
    $this->gt = country('GT')->id;
    $this->city = city();
    $this->insertDoctor = function (string $id, array $attrs = []) {
        DB::table('doctors')->insert([
            'id' => $id, 'country_id' => $this->cr, 'first_name' => 'X', 'last_name' => 'Y',
            'slug' => strtolower($id), 'name_normalized' => 'x y', ...$attrs,
        ]);
    };
});

describe('tipos y convenciones', function () {
    it('el dominio ulid rechaza valores fuera de Crockford base32', function (string $value) {
        violates('ulid_check', fn () => DB::select('select ?::ulid', [$value]));
    })->with(['not-a-ulid', '81J8Z3K9QXW5V7N2M4P6R8T0AB', '01J8Z3K9QXW5V7N2M4P6R8T0AI', '01j8z3k9qxw5v7n2m4p6r8t0ab']);

    it('no hay columnas timestamp sin zona horaria', function () {
        expect(DB::scalar("select count(*) from information_schema.columns where table_schema = 'public' and data_type = 'timestamp without time zone'"))->toBe(0);
    });

    it('no hay tipos ENUM nativos', function () {
        expect(DB::scalar("select count(*) from pg_type t join pg_namespace n on n.oid = t.typnamespace where n.nspname = 'public' and t.typtype = 'e'"))->toBe(0);
    });

    it('existen las 27 tablas propias más activity_log', function () {
        $infra = ['migrations', 'password_reset_tokens', 'sessions', 'jobs', 'job_batches', 'failed_jobs', 'cache', 'cache_locks', 'personal_access_tokens'];
        $own = collect(DB::select("select table_name from information_schema.tables where table_schema = 'public' and table_type = 'BASE TABLE'"))
            ->pluck('table_name')->diff($infra);

        expect($own)->toHaveCount(28);
    });

    it('es_unaccent iguala acentos y aplica stemming español', function () {
        expect(DB::scalar("select to_tsvector('es_unaccent', 'cardiólogo') = to_tsvector('es_unaccent', 'cardiologo')"))->toBeTrue();
    });
});

describe('índices únicos parciales (§19)', function () {
    it('un usuario gestiona como máximo un médico', function () {
        $user = admin()->id;
        ($this->insertDoctor)(ulid('D1'), ['user_id' => $user]);
        violates('doctors_user_uniq', fn () => ($this->insertDoctor)(ulid('D2'), ['user_id' => $user]));
    });

    it('la licencia es única por país, pero se repite entre países y admite varios NULL', function () {
        ($this->insertDoctor)(ulid('D1'), ['license_number' => 'MED-1']);
        ($this->insertDoctor)(ulid('D2'), ['license_number' => 'MED-1', 'country_id' => $this->gt]);
        ($this->insertDoctor)(ulid('D3'));
        ($this->insertDoctor)(ulid('D4'));

        violates('doctors_country_license_uniq', fn () => ($this->insertDoctor)(ulid('D5'), ['license_number' => 'MED-1']));
    });

    it('una sola especialidad principal por médico', function () {
        ($this->insertDoctor)(ulid('D1'));
        DB::table('doctor_specialties')->insert(['doctor_id' => ulid('D1'), 'specialty_id' => specialty()->id, 'is_primary' => true]);

        violates('doctor_specialties_one_primary_uniq', fn () => DB::table('doctor_specialties')->insert(['doctor_id' => ulid('D1'), 'specialty_id' => specialty('pediatria')->id, 'is_primary' => true]));
    });

    it('una sola ubicación principal por médico', function () {
        ($this->insertDoctor)(ulid('D1'));
        foreach (['P1', 'P2'] as $l) {
            DB::table('locations')->insert(['id' => ulid($l), 'country_id' => $this->cr, 'region_id' => $this->city->region_id, 'city_id' => $this->city->id, 'address' => $l, 'address_normalized' => $l]);
        }
        DB::table('doctor_locations')->insert(['doctor_id' => ulid('D1'), 'location_id' => ulid('P1'), 'is_primary' => true]);

        violates('doctor_locations_one_primary_uniq', fn () => DB::table('doctor_locations')->insert(['doctor_id' => ulid('D1'), 'location_id' => ulid('P2'), 'is_primary' => true]));
    });

    it('un solo contacto principal por tipo', function () {
        ($this->insertDoctor)(ulid('D1'));
        DB::table('doctor_contacts')->insert(['id' => ulid('K1'), 'doctor_id' => ulid('D1'), 'type' => 'phone', 'value' => 'a', 'value_normalized' => '+1', 'is_primary' => true]);

        violates('doctor_contacts_one_primary_uniq', fn () => DB::table('doctor_contacts')->insert(['id' => ulid('K2'), 'doctor_id' => ulid('D1'), 'type' => 'phone', 'value' => 'b', 'value_normalized' => '+2', 'is_primary' => true]));
    });

    it('un claim aprobado por ficha y un pendiente por usuario, pero varios usuarios pendientes', function () {
        ($this->insertDoctor)(ulid('D1'));
        [$a, $b] = [doctorUser('a@t.l')->id, doctorUser('b@t.l')->id];
        DB::table('doctor_claims')->insert([['id' => ulid('C1'), 'doctor_id' => ulid('D1'), 'user_id' => $a], ['id' => ulid('C2'), 'doctor_id' => ulid('D1'), 'user_id' => $b]]);

        violates('doctor_claims_one_pending_per_user_uniq', fn () => DB::table('doctor_claims')->insert(['id' => ulid('C3'), 'doctor_id' => ulid('D1'), 'user_id' => $a]));
        violates('doctor_claims_one_approved_uniq', fn () => DB::table('doctor_claims')->whereIn('id', [ulid('C1'), ulid('C2')])->update(['status' => 'approved', 'reviewed_at' => now()]));
    });

    it('slug_redirects no admite dos redirects del mismo slug de especialidad aunque country_id sea NULL', function () {
        $row = fn (string $id) => ['id' => ulid($id), 'entity_type' => 'specialty', 'entity_id' => specialty()->id, 'old_slug' => 'cardio'];
        DB::table('slug_redirects')->insert($row('R1'));

        violates('slug_redirects_uniq', fn () => DB::table('slug_redirects')->insert($row('R2')));
    });
});

describe('CHECK de invariantes de doctors', function () {
    it('rechaza estados imposibles', function (array $attrs, string $constraint) {
        violates($constraint, fn () => ($this->insertDoctor)(ulid('D9'), $attrs));
    })->with([
        'fusionado sin destino' => [['status' => 'merged'], 'doctors_merged_requires_target_chk'],
        'reclamado sin usuario' => [['claim_status' => 'claimed'], 'doctors_claimed_requires_user_chk'],
        'verificado sin traza' => [['verification_status' => 'verified'], 'doctors_verified_requires_trace_chk'],
        'publicado en borrador' => [['published_at' => now()], 'doctors_published_requires_active_chk'],
        'estado desconocido' => [['status' => 'deleted'], 'doctors_status_chk'],
    ]);

    it('no se fusiona consigo mismo', function () {
        ($this->insertDoctor)(ulid('D1'));
        violates('doctors_not_merged_into_self_chk', fn () => DB::table('doctors')->where('id', ulid('D1'))->update(['merged_into_doctor_id' => ulid('D1')]));
    });
});

describe('geografía', function () {
    it('una ciudad no puede colgar de una región de otro país', function () {
        violates('cities_region_country_fk', fn () => DB::table('cities')->insert([
            'id' => ulid('T9'), 'country_id' => $this->gt, 'region_id' => $this->city->region_id, 'name' => 'X', 'slug' => 'x',
        ]));
    });

    it('una ubicación no puede tener latitud sin longitud', function () {
        violates('locations_coordinates_chk', fn () => DB::table('locations')->insert([
            'id' => ulid('P9'), 'country_id' => $this->cr, 'region_id' => $this->city->region_id, 'city_id' => $this->city->id,
            'address' => 'x', 'address_normalized' => 'x', 'latitude' => 9.9,
        ]));
    });
});
