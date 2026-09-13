<?php

namespace App\Actions\Recetas;

use Illuminate\Support\Facades\Storage;

class ImagenRevision
{
    /** @return array{ruta: string, mime: string}|null */
    public function localizar(mixed $imagen, int $recetaId): ?array
    {
        if (! is_string($imagen) || ! preg_match('#\\Arecetas/'.$recetaId.'/[a-zA-Z0-9_-]+\\.(png|jpg|jpeg|webp)\\z#', $imagen)) {
            return null;
        }
        $disco = Storage::disk('local');
        $base = realpath($disco->path('recetas/'.$recetaId));
        $ruta = realpath($disco->path($imagen));
        if ($base === false || $ruta === false || ! is_file($ruta)
            || ! str_starts_with(strtolower($ruta), strtolower($base.DIRECTORY_SEPARATOR))) {
            return null;
        }
        $mime = mime_content_type($ruta);
        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            return null;
        }

        return ['ruta' => $ruta, 'mime' => $mime];
    }
}
