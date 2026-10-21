<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'categoria_id',
    'nombre',
])]
class Subcategoria extends Model
{
    use HasFactory;

    protected $table = 'subcategorias';

    protected function casts(): array
    {
        return [
            'categoria_id' => 'integer',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function recetas(): HasMany
    {
        return $this->hasMany(Receta::class, 'subcategoria_id');
    }

    public function tieneRecetasAsociadas(): bool
    {
        return $this->recetas()->withTrashed()->exists();
    }
}
