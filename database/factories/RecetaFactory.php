<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Receta;
use App\Models\Subcategoria;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Receta> */
class RecetaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => 'Arroz con verduras',
            'descripcion' => 'Arroz casero con verduras frescas.',
            'imagen' => 'pendiente.png',
            'porciones' => 2,
            'tiempo_preparacion' => 25,
            'tips' => null,
            'categoria_id' => fn (array $atributos): ?int => isset($atributos['subcategoria_id']) && ! $atributos['subcategoria_id'] instanceof Closure
                ? Subcategoria::query()->whereKey($atributos['subcategoria_id'])->value('categoria_id')
                : Categoria::factory()->create()->id,
            'subcategoria_id' => fn (array $atributos): int => Subcategoria::factory()->create([
                'categoria_id' => isset($atributos['categoria_id']) && ! $atributos['categoria_id'] instanceof Closure
                    ? $atributos['categoria_id']
                    : Categoria::factory()->create()->id,
            ])->id,
            'creado_por' => User::factory(),
            'version' => 1,
        ];
    }

    public function publicada(): static
    {
        return $this->state(fn () => ['publicada_en' => now()]);
    }

    public function sinClasificacion(): static
    {
        return $this->state(fn () => [
            'publicada_en' => now(),
            'categoria_id' => null,
            'subcategoria_id' => null,
        ])->afterMaking(function (Receta $receta): void {
            $receta->permitirSinClasificacionHistorica();
        });
    }
}
