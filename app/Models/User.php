<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'foto_perfil', 'rol', 'activo'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    public function esAdministrador(): bool
    {
        return $this->rol === 'administrador';
    }

    public function estaActivo(): bool
    {
        return (bool) $this->activo;
    }

    protected function iniciales(): Attribute
    {
        return Attribute::make(
            get: function () {
                $palabras = preg_split('/\s+/', trim((string) $this->name));
                if (empty($palabras) || empty($palabras[0])) {
                    return 'U';
                }
                if (count($palabras) === 1) {
                    return mb_strtoupper(mb_substr($palabras[0], 0, 2));
                }

                return mb_strtoupper(mb_substr($palabras[0], 0, 1).mb_substr($palabras[count($palabras) - 1], 0, 1));
            }
        );
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
        )
            ->withTimestamps()
            ->withTrashed();
    }

    public function solicitudesRevision(): HasMany
    {
        return $this->hasMany(
            SolicitudRevision::class,
            'solicitado_por'
        );
    }

    public function valoraciones(): HasMany
    {
        return $this->hasMany(
            Valoracion::class,
            'usuario_id'
        );
    }

    public function recuperacionesPassword(): HasMany
    {
        return $this->hasMany(
            RecuperacionPassword::class,
            'user_id'
        );
    }

    protected function fotoPerfilUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->foto_perfil ? route('api.v1.perfil.foto') : null,
        );
    }
}
