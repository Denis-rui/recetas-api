<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'foto_perfil'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'users';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    public function recetasCreadas(): HasMany
    {
        return $this->hasMany(Receta::class, 'creado_por');
    }

    public function recetasActualizadas(): HasMany
    {
        return $this->hasMany(Receta::class, 'actualizado_por');
    }

    public function favoritos(): BelongsToMany
    {
        return $this->belongsToMany(
            Receta::class,
            'favoritos',
            'usuario_id',
            'receta_id',
        )->withTimestamps();
    }
}