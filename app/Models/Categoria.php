<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['nombre'])]
class Categoria extends Model
{
    protected $table = 'categorias';

    public function recetas(): BelongsToMany
    {
        return $this->belongsToMany(
            Receta::class,
            'categoria_receta',
            'categoria_id',
            'receta_id',
        )->withTimestamps();
    }
}
