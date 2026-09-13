<?php

namespace App\Actions\Recetas;

use App\Models\Receta;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GuardarImagenReceta
{
    private const FORMATOS_VALIDOS = ['jpg', 'jpeg', 'png', 'webp'];

    private const MIMES_VALIDOS = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * Valida y almacena una imagen para la receta en el almacenamiento privado (disco local).
     *
     * @return array{ruta: string, mime: string}
     *
     * @throws ValidationException
     */
    public function ejecutar(UploadedFile $archivo, int $recetaId): array
    {
        $extension = strtolower($archivo->getClientOriginalExtension());
        if (! in_array($extension, self::FORMATOS_VALIDOS, true)) {
            throw ValidationException::withMessages([
                'imagen' => 'El formato de la imagen no es compatible. Use JPG, PNG o WEBP.',
            ]);
        }

        if ($archivo->getSize() > 2048 * 1024) {
            throw ValidationException::withMessages([
                'imagen' => 'La imagen supera el tamaño máximo permitido de 2 MB.',
            ]);
        }

        $rutaReal = $archivo->getRealPath();
        if (! $rutaReal || ! is_file($rutaReal)) {
            throw ValidationException::withMessages([
                'imagen' => 'No se pudo leer el archivo de la imagen.',
            ]);
        }

        $mimeReal = mime_content_type($rutaReal);
        if (! in_array($mimeReal, self::MIMES_VALIDOS, true)) {
            throw ValidationException::withMessages([
                'imagen' => 'El contenido real del archivo no corresponde a una imagen válida.',
            ]);
        }

        $nombreArchivo = Str::random(40).'.'.$extension;
        $directorio = 'recetas/'.$recetaId;
        $rutaAlmacenada = $archivo->storeAs($directorio, $nombreArchivo, 'local');

        if (! $rutaAlmacenada || ! Storage::disk('local')->exists($rutaAlmacenada)) {
            throw ValidationException::withMessages([
                'imagen' => 'No se pudo guardar la imagen en el almacenamiento del servidor.',
            ]);
        }

        return [
            'ruta' => $rutaAlmacenada,
            'mime' => $mimeReal,
        ];
    }

    /**
     * Elimina con seguridad un archivo si existe en el disco local y pertenece a la receta dada.
     */
    public function eliminarSiExiste(?string $ruta, int $recetaId): void
    {
        if (! is_string($ruta) || ! preg_match('#\Arecetas/'.$recetaId.'/[a-zA-Z0-9_-]+\.(png|jpg|jpeg|webp)\z#', $ruta)) {
            return;
        }

        if (Storage::disk('local')->exists($ruta)) {
            Storage::disk('local')->delete($ruta);
        }
    }
}
