<?php

/*
| Autorización por permiso (MODELO-IDENTIDAD.md §5): el admin entra a todo, un usuario
| con rol doctor no entra al backoffice, y el panel solo vive en su hostname.
*/

function panel(string $path): string
{
    return 'http://admin.localhost'.$path;
}

it('un visitante anónimo es redirigido al login', function () {
    $this->get(panel('/'))->assertRedirect(panel('/login'));
});

it('el panel no responde en el dominio público', function () {
    $this->get('http://localhost/login')->assertNotFound();
});

it('el admin ve cada sección y un usuario médico recibe 403', function (string $path) {
    $admin = admin();
    $doctor = doctorUser();

    $this->actingAs($admin)->get(panel($path))->assertOk();
    $this->actingAs($doctor)->get(panel($path))->assertForbidden();
})->with([
    '/', '/doctors', '/doctors/create', '/locations', '/locations/create',
    '/specialties', '/countries', '/regions', '/cities', '/languages',
    '/users', '/users/create', '/roles', '/activities',
    '/doctor-suppressions', '/doctor-suppressions/create',
    '/import-batches', '/import-batches/create',
]);

it('una cuenta suspendida no entra aunque conserve el rol', function () {
    admin('otro@x.com');
    $admin = admin();
    $admin->forceFill(['status' => 'suspended'])->save();

    $this->actingAs($admin)->get(panel('/'))->assertForbidden();
});

it('no existen rutas de edición para lo que nunca se edita', function (string $path) {
    $this->actingAs(admin())->get(panel($path))->assertNotFound();
})->with(['/countries/create', '/doctor-suppressions/x/edit']);

it('las páginas de ficha y detalle cargan con datos', function () {
    $admin = admin();
    $doctor = publishedDoctor();

    $this->actingAs($admin)->get(panel("/doctors/{$doctor->id}/edit"))->assertOk()->assertSee('Requisitos para publicar');
    $this->actingAs($admin)->get(panel('/locations/'.$doctor->locations()->first()->id.'/edit'))->assertOk();
});
