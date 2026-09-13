<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Ingrediente;
use App\Models\Receta;
use App\Models\SolicitudRevision;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<SolicitudRevision> */
class SolicitudRevisionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'receta_id' => Receta::factory(),
            'solicitado_por' => fn (array $datos) => Receta::withTrashed()->findOrFail($datos['receta_id'])->creado_por,
            'tipo' => 'publicacion', 'estado' => 'pendiente',
            'version_base' => fn (array $datos) => Receta::withTrashed()->findOrFail($datos['receta_id'])->version,
            'clave_idempotencia' => fn () => (string) Str::uuid(),
            'contenido' => fn (array $datos) => [
                'nombre' => 'Arroz con verduras mejorado', 'descripcion' => 'Una preparación casera y sencilla.',
                'imagen' => 'recetas/'.$datos['receta_id'].'/demo.png',
                'porciones' => 3, 'tiempo_preparacion' => 30, 'tips' => 'Dejar reposar cinco minutos.',
                'categorias' => [Categoria::factory()->create()->id],
                'ingredientes' => [['ingrediente_id' => Ingrediente::factory()->create()->id, 'cantidad' => '1.500', 'unidad' => 'tazas', 'notas' => 'Lavado', 'orden' => 1]],
                'pasos' => [['orden' => 1, 'instruccion' => 'Cocinar a fuego lento.']],
            ],
        ];
    }

    public function correccion(): static
    {
        return $this->state(fn () => ['receta_id' => Receta::factory()->publicada(), 'tipo' => 'correccion']);
    }
}
