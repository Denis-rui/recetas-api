<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

#[Fillable([
    'nombre',
    'descripcion',
    'imagen',
    'porciones',
    'tiempo_preparacion',
    'tips',
    'categoria_id',
    'subcategoria_id',
])]
class Receta extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'recetas';

    protected bool $permiteSinClasificacionHistorica = false;

    protected function casts(): array
    {
        return [
            'porciones' => 'integer',
            'tiempo_preparacion' => 'integer',
            'categoria_id' => 'integer',
            'subcategoria_id' => 'integer',
            'publicada_en' => 'datetime',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Receta $receta): void {
            $tieneCategoria = $receta->categoria_id !== null;
            $tieneSubcategoria = $receta->subcategoria_id !== null;

            if (! $tieneCategoria && ! $tieneSubcategoria) {
                $esHistoricaPublicadaSinClasificacion = ($receta->permiteSinClasificacionHistorica && $receta->publicada_en !== null)
                    || (
                        $receta->exists
                        && $receta->getOriginal('categoria_id') === null
                        && $receta->getOriginal('subcategoria_id') === null
                        && ($receta->publicada_en !== null || $receta->getOriginal('publicada_en') !== null)
                    );

                if (! $esHistoricaPublicadaSinClasificacion) {
                    throw new InvalidArgumentException('Toda receta nueva requiere una categoría y una subcategoría.');
                }

                return;
            }

            if (! $tieneCategoria || ! $tieneSubcategoria) {
                throw new InvalidArgumentException('La receta debe tener tanto categoría como subcategoría.');
            }

            $perteneceACategoria = Subcategoria::query()
                ->whereKey($receta->subcategoria_id)
                ->where('categoria_id', $receta->categoria_id)
                ->exists();

            if (! $perteneceACategoria) {
                throw new InvalidArgumentException('La subcategoría seleccionada no pertenece a la categoría de la receta.');
            }
        });
    }

    public function permitirSinClasificacionHistorica(bool $permitir = true): static
    {
        $this->permiteSinClasificacionHistorica = $permitir;

        return $this;
    }

    /**
     * @param  Builder<Receta>  $query
     * @return Builder<Receta>
     */
    public function scopePublicada(Builder $query): Builder
    {
        return $query->whereNotNull('publicada_en');
    }

    public function estaPublicada(): bool
    {
        return $this->publicada_en !== null && ! $this->trashed();
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function subcategoria(): BelongsTo
    {
        return $this->belongsTo(Subcategoria::class, 'subcategoria_id');
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

    public function solicitudesRevision(): HasMany
    {
        return $this->hasMany(SolicitudRevision::class, 'receta_id');
    }

    public function valoraciones(): HasMany
    {
        return $this->hasMany(Valoracion::class, 'receta_id');
    }

    public function eliminadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'eliminado_por');
    }
}
