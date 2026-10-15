<?php

use App\Domain\Directory\Actions\SuspendDoctorAction;
use App\Domain\Directory\Actions\UpdateSlugAction;
use App\Models\DoctorContactEvent;
use App\Models\Specialty;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;

/*
| Sitio público (AGENTS.md, regla 2): sin sesión ni cookies, cacheable en el CDN, y
| nunca un 404 por un slug que fue válido (§3).
*/

beforeEach(function () {
    activeCountry();
    $this->doctor = publishedDoctor();
});

function assertCacheablePublic(TestResponse $response, int $sharedMaxAge = 86400): void
{
    expect($response->headers->getCookies())->toBe([])
        ->and($response->headers->get('Cache-Control'))->toContain('public')
        ->and($response->headers->get('Cache-Control'))->toContain("s-maxage={$sharedMaxAge}");
}

describe('sin sesión y cacheable', function () {
    it('sirve todas las páginas sin cookies y con caché pública', function (string $url) {
        $response = $this->get($url)->assertOk();

        assertCacheablePublic($response);
    })->with([
        'portada' => '/',
        'país' => '/costa-rica',
        'especialidad' => '/costa-rica/cardiologia',
        'listado' => '/costa-rica/cardiologia/escazu',
        'ficha' => '/costa-rica/medicos/ana-rojas',
    ]);

    it('da un s-maxage corto a las variantes con query string, que no se pueden purgar', function () {
        assertCacheablePublic($this->get('/costa-rica/cardiologia/escazu?idioma=en')->assertOk(), 600);
    });

    it('no sirve países inactivos', function () {
        $this->get('/guatemala')->assertNotFound();
        $this->get('/guatemala/cardiologia')->assertNotFound();
    });
});

describe('home del país', function () {
    it('muestra médicos reales, especialidades con médicos y el CTA para médicos', function () {
        $this->get('/costa-rica')
            ->assertOk()
            ->assertSee('/costa-rica/medicos/ana-rojas', false)
            ->assertSee('/costa-rica/cardiologia', false)
            ->assertSee('Quiero aparecer en MeeMedico')
            ->assertSee('mailto:'.config('meemedico.operations.email'), false)
            ->assertSee('Próximamente');
    });

    it('no carga la foto grande en móvil: solo hay fuente para lg', function () {
        $this->get('/costa-rica')
            ->assertSee('media="(min-width: 64rem)"', false)
            ->assertSee('media="(max-width: 63.99rem)"', false);
    });
});

describe('ficha', function () {
    it('muestra los datos públicos, el contacto y el JSON-LD de Physician', function () {
        $this->get('/costa-rica/medicos/ana-rojas')
            ->assertOk()
            ->assertSee('Ana Rojas')
            ->assertSee('tel:+50622223333', false)
            ->assertSee('"@type":"Physician"', false)
            ->assertSee('data-contact="phone"', false)
            ->assertSee('<meta name="robots" content="index, follow">', false)
            ->assertDontSee('data-island', false);
    });

    it('responde 404 a un borrador y a una ficha suspendida', function () {
        publishableDoctor(['first_name' => 'Eva', 'last_name' => 'Borrador']);
        app(SuspendDoctorAction::class)->execute($this->doctor, null, 'Prueba');

        $this->get('/costa-rica/medicos/eva-borrador')->assertNotFound();
        $this->get('/costa-rica/medicos/ana-rojas')->assertNotFound();
    });

    it('redirige con 301 un slug antiguo', function () {
        app(UpdateSlugAction::class)->execute($this->doctor, 'ana-rojas-cardiologa');

        $this->get('/costa-rica/medicos/ana-rojas')
            ->assertStatus(301)
            ->assertRedirect('/costa-rica/medicos/ana-rojas-cardiologa');
    });

    it('redirige con 301 una ficha fusionada a la superviviente', function () {
        $survivor = publishedDoctorIn('Escazú', data: ['first_name' => 'Ana', 'last_name' => 'Rojas Mora']);
        $this->doctor->forceFill(['status' => 'merged', 'merged_into_doctor_id' => $survivor->id])->save();

        $this->get('/costa-rica/medicos/ana-rojas')
            ->assertStatus(301)
            ->assertRedirect("/costa-rica/medicos/{$survivor->slug}");
    });
});

describe('listado', function () {
    it('muestra a los médicos de la especialidad en la ciudad', function () {
        $this->get('/costa-rica/cardiologia/escazu')
            ->assertOk()
            ->assertSee('Cardiología en Escazú')
            ->assertSee('/costa-rica/medicos/ana-rojas', false)
            ->assertSee('"@type":"ItemList"', false);
    });

    it('no indexa las variantes filtradas ni los listados vacíos', function () {
        $this->get('/costa-rica/cardiologia/escazu?idioma=en')->assertSee('noindex, follow', false);
        $this->get('/costa-rica/pediatria/escazu')->assertOk()->assertSee('noindex, follow', false);
    });

    it('lleva una subespecialidad al listado de su padre con ?sub', function () {
        Specialty::create(['parent_id' => specialty('cardiologia')->id, 'name' => 'Cardiología Intervencionista', 'slug' => 'cardiologia-intervencionista']);

        $this->get('/costa-rica/cardiologia-intervencionista/escazu')
            ->assertStatus(301)
            ->assertRedirect('/costa-rica/cardiologia/escazu?sub=cardiologia-intervencionista');
    });

    it('responde 404 a una página fuera de rango y a una ciudad de otro país', function () {
        city('GT', 'Mixco');

        $this->get('/costa-rica/cardiologia/escazu?page=9')->assertNotFound();
        $this->get('/costa-rica/cardiologia/mixco')->assertNotFound();
    });
});

describe('búsqueda', function () {
    it('lleva una frase con especialidad y ciudad a su listado canónico', function () {
        $this->get('/costa-rica/buscar?q='.urlencode('cardiólogos en Escazú que hablen inglés'))
            ->assertRedirect('/costa-rica/cardiologia/escazu?idioma=en');
    });

    it('lleva una frase con solo la especialidad a su página', function () {
        $this->get('/costa-rica/buscar?q=cardiologa')->assertRedirect('/costa-rica/cardiologia');
    });

    it('muestra lo que entendió como chips que se pueden quitar, sin indexar', function () {
        $this->get('/costa-rica/buscar?q='.urlencode('Rojas en Escazú'))
            ->assertOk()
            ->assertSee('noindex, follow', false)
            ->assertSee('Escazú')
            ->assertSee('«Rojas»')
            ->assertSee('/costa-rica/medicos/ana-rojas', false);
    });
});

describe('API', function () {
    it('sugiere la interpretación de la frase con su número de resultados', function () {
        $this->getJson('/api/v1/costa-rica/suggest?q='.urlencode('cardiologo escazu'))
            ->assertOk()
            ->assertJsonPath('data.interpretation.label', 'Cardiología en Escazú')
            ->assertJsonPath('data.interpretation.meta', '1 médico')
            ->assertJsonPath('data.interpretation.url', '/costa-rica/cardiologia/escazu');
    });

    it('completa la última palabra a medio escribir', function () {
        $this->getJson('/api/v1/costa-rica/suggest?q='.urlencode('cardiologo en esca'))
            ->assertOk()
            ->assertJsonPath('data.interpretation.label', 'Cardiología en Escazú')
            ->assertJsonPath('data.interpretation.url', '/costa-rica/cardiologia/escazu');
    });

    it('sugiere especialidades y médicos mientras se escribe', function () {
        $this->getJson('/api/v1/costa-rica/suggest?q=cardio')
            ->assertOk()
            ->assertJsonPath('data.specialties.0.label', 'Cardiología');

        $this->getJson('/api/v1/costa-rica/suggest?q=roj')
            ->assertOk()
            ->assertJsonPath('data.doctors.0.url', '/costa-rica/medicos/ana-rojas');
    });

    it('cachea las facetas como escalares, no como modelos', function () {
        // Con el driver database o redis, un modelo serializado vuelve como objeto
        // incompleto (cache.serializable_classes). El driver array de los tests no lo nota.
        $this->getJson('/api/v1/costa-rica/suggest?q=cardio')->assertOk();

        expect(Cache::get('search:specialty-facets:'.country()->id))->toBe([[specialty()->id, 1]])
            ->and(Cache::get('search:city-facets:'.country()->id))->toBe([[city()->id, 1]]);
    });

    it('registra un contacto de una ficha publicada y rechaza el de una que no lo está', function () {
        $draft = publishableDoctor(['first_name' => 'Eva', 'last_name' => 'Borrador']);

        $this->postJson('/api/v1/contact-events', ['doctor' => $this->doctor->id, 'type' => 'whatsapp', 'source' => 'listing'])
            ->assertNoContent();
        $this->postJson('/api/v1/contact-events', ['doctor' => $draft->id, 'type' => 'phone'])
            ->assertStatus(422);

        expect(DoctorContactEvent::count())->toBe(1);
    });
});
