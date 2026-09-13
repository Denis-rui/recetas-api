<?php

namespace App\Models;

use Database\Factories\OperacionRecetaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OperacionReceta extends Model
{
    /** @use HasFactory<OperacionRecetaFactory> */
    use HasFactory;

    protected $table = 'operaciones_receta';
}
