<?php

use App\Models\SolicitudRevision;
use App\Models\User;

test('no acepta notaciones numericas impropias en rutas web', function (string $formato) {
    $usuario = User::factory()->create();
    $solicitud = SolicitudRevision::factory()->create();
    $admin = User::factory()->create(['rol' => 'administrador', 'activo' => true]);
    $this->actingAs($admin)->get('/usuarios/'.sprintf($formato, $usuario->id).'/editar')->assertNotFound();
    $this->get('/revision-recetas/'.sprintf($formato, $solicitud->id))->assertNotFound();
})->with(['%s.0', '%se0', '+%s', '0%s']);

test('valida la pagina del listado de revisiones', function (string $pagina) {
    $admin = User::factory()->create(['rol' => 'administrador', 'activo' => true]);
    $this->actingAs($admin)->get('/revision-recetas?page='.$pagina)->assertRedirect('/revision-recetas')->assertSessionHasErrors('page');
})->with(['-1', '0', '1.5', '1e2', '9223372036854775807']);
