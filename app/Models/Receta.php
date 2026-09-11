<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'nombre',
    'descripcion',
    'imagen',
    'porciones',
    'tiempo_preparacion',
])]
class Receta extends Model
{
    use SoftDeletes;

    protected $table = 'recetas';

    protected function casts(): array
    {
        return [
            'porciones' => 'integer',
            'tiempo_preparacion' => 'integer',
        ];
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function ultimoEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }

    public function ingredientes(): BelongsToMany
    {
        return $this->belongsToMany(
            Ingrediente::class,
            'ingrediente_receta',
            'receta_id',
            'ingrediente_id',
        )
            ->withPivot(['cantidad', 'unidad', 'notas', 'orden'])
            ->withTimestamps()
            ->orderByPivot('orden');
    }

    public function categorias(): BelongsToMany
    {
        return $this->belongsToMany(
            Categoria::class,
            'categoria_receta',
            'receta_id',
            'categoria_id',
        )->withTimestamps();
    }

    public function pasos(): HasMany
    {
        return $this->hasMany(PasoReceta::class, 'receta_id')
            ->orderBy('orden');
    }

    public function usuariosQueLaGuardaron(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'favoritos',
            'receta_id',
            'usuario_id',
        )->withTimestamps();
    }
}