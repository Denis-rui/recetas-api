<?php

namespace Database\Factories;

use App\Models\OperacionReceta;
use App\Models\Receta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperacionReceta>
 */
class OperacionRecetaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'receta_id' => Receta::factory(),
            'usuario_id' => fn (array $datos) => Receta::withTrashed()->findOrFail($datos['receta_id'])->creado_por,
            'clave_idempotencia' => fake()->uuid(),
            'operacion' => 'publicacion',
            'huella_peticion' => hash('sha256', '[]'),
        ];
    }
}
