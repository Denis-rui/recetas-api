<?php

namespace Database\Factories;

use App\Models\Receta;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Receta> */
class RecetaFactory extends Factory
{
    public function definition(): array
    {
        return ['nombre' => 'Arroz con verduras', 'descripcion' => 'Arroz casero con verduras frescas.', 'imagen' => 'pendiente.png', 'porciones' => 2, 'tiempo_preparacion' => 25, 'tips' => null, 'creado_por' => User::factory(), 'version' => 1];
    }

    public function publicada(): static
    {
        return $this->state(fn () => ['publicada_en' => now()]);
    }
}
