<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['orden', 'instruccion'])]
class PasoReceta extends Model
{
    protected $table = 'pasos_receta';

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
        ];
    }

    public function receta(): BelongsTo
    {
        return $this->belongsTo(Receta::class, 'receta_id');
    }
}
