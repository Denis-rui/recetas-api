<?php

use App\Actions\Recetas\ProcesarRevision;
use App\Models\SolicitudRevision;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('local');
    $this->solicitud = SolicitudRevision::factory()->correccion()->create();
    Storage::disk('local')->put($this->solicitud->contenido['imagen'], base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a8V8AAAAASUVORK5CYII='));
    $this->admin = User::factory()->create(['rol' => 'administrador', 'activo' => true]);
});

test('exige clasificacion manual menor para aprobar una correccion', function (mixed $confirmacion) {
    $antes = $this->solicitud->receta->toArray();
    $this->actingAs($this->admin)->post(route('revision-recetas.aprobar', $this->solicitud), [
        'confirmar_correccion_menor' => $confirmacion,
    ])->assertSessionHasErrors('confirmar_correccion_menor');
    expect($this->solicitud->fresh()->estado)->toBe('pendiente');
    expect($this->solicitud->receta->fresh()->toArray())->toEqual($antes);
})->with([null, false, 'importante']);

test('la accion tampoco permite omitir la clasificacion manual', function () {
    expect(fn () => app(ProcesarRevision::class)->ejecutar($this->admin, $this->solicitud, 'aprobar'))
        ->toThrow(ValidationException::class);
    expect($this->solicitud->fresh()->estado)->toBe('pendiente');
});

test('permite aprobar una correccion confirmada y rechazar una transformacion', function () {
    $this->actingAs($this->admin)->get(route('revision-recetas.show', $this->solicitud))
        ->assertOk()->assertSee('confirmar_correccion_menor');
    $this->post(route('revision-recetas.aprobar', $this->solicitud), ['confirmar_correccion_menor' => '1'])
        ->assertSessionHas('exito');
    expect($this->solicitud->fresh()->estado)->toBe('aprobada');

    $otra = SolicitudRevision::factory()->correccion()->create();
    $this->post(route('revision-recetas.rechazar', $otra), ['motivo_rechazo' => 'Transforma la preparación. Crea una receta nueva.'])
        ->assertSessionHas('exito');
    expect($otra->fresh()->estado)->toBe('rechazada');
    expect($otra->receta->fresh()->version)->toBe(1);
});
