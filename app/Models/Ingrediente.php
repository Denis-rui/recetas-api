<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['nombre'])]
class Ingrediente extends Model
{
    use HasFactory;

    protected $table = 'ingredientes';

    public function recetas(): BelongsToMany
    {
        return $this->belongsToMany(
            Receta::class,
            'ingrediente_receta',
            'ingrediente_id',
            'receta_id',
        )
            ->withPivot(['cantidad', 'unidad', 'notas', 'orden'])
            ->withTimestamps();
    }
}
