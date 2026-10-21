<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nombre'])]
class Categoria extends Model
{
    use HasFactory;

    protected $table = 'categorias';

    public function subcategorias(): HasMany
    {
        return $this->hasMany(Subcategoria::class, 'categoria_id');
    }

    public function recetas(): HasMany
    {
        return $this->hasMany(Receta::class, 'categoria_id');
    }

    public function tieneRecetasAsociadas(): bool
    {
        return $this->recetas()->withTrashed()->exists()
            || Receta::withTrashed()->whereIn('subcategoria_id', $this->subcategorias()->select('id'))->exists();
    }
}
