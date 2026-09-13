<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['contenido'])]
class SolicitudRevision extends Model
{
    use HasFactory;

    protected $table = 'solicitudes_revision';

    protected function casts(): array
    {
        return [
            'contenido' => 'array',
            'version_base' => 'integer',
            'revisada_en' => 'datetime',
            'cancelada_en' => 'datetime',
        ];
    }

    public function receta(): BelongsTo
    {
        return $this->belongsTo(Receta::class, 'receta_id')->withTrashed();
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por');
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }
}
