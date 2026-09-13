<?php

namespace Database\Factories;

use App\Models\Receta;
use App\Models\User;
use App\Models\Valoracion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Valoracion> */
class ValoracionFactory extends Factory
{
    public function definition(): array
    {
        return ['receta_id' => Receta::factory()->publicada(), 'usuario_id' => User::factory(), 'puntuacion' => 4];
    }
}
