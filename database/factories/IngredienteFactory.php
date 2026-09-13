<?php

namespace Database\Factories;

use App\Models\Ingrediente;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Ingrediente> */
class IngredienteFactory extends Factory
{
    public function definition(): array
    {
        return ['nombre' => fake()->unique()->word()];
    }
}
