<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecuperacionPassword extends Model
{
    use HasFactory;

    protected $table = 'recuperaciones_password';

    protected $fillable = [
        'user_id',
        'email',
        'codigo_hash',
        'intentos',
        'codigo_expira_en',
        'codigo_verificado_en',
        'token_recuperacion_hash',
        'token_expira_en',
        'usado_en',
        'invalidado_en',
    ];

    protected function casts(): array
    {
        return [
            'intentos' => 'integer',
            'codigo_expira_en' => 'datetime',
            'codigo_verificado_en' => 'datetime',
            'token_expira_en' => 'datetime',
            'usado_en' => 'datetime',
            'invalidado_en' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function codigoHaExpirado(): bool
    {
        return now()->greaterThan($this->codigo_expira_en);
    }

    public function tokenHaExpirado(): bool
    {
        return $this->token_expira_en !== null && now()->greaterThan($this->token_expira_en);
    }

    public function estaDisponible(): bool
    {
        return $this->invalidado_en === null && $this->usado_en === null;
    }
}

