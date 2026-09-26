<?php

namespace App\Actions\Recetas;

use App\Models\Receta;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

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

        // --- NUEVA LÓGICA DE CONVERSIÓN CON INTERVENTION v4 ---
        $nombreArchivo = Str::random(40).'.webp';
        $directorio = 'recetas/'.$recetaId;
        $rutaAlmacenada = $directorio.'/'.$nombreArchivo;

        try {
            $manager = new ImageManager(new Driver);
            $imagen = $manager->decode($rutaReal);

            // Redimensionar respetando el aspecto, máximo 1024 de ancho o alto
            $imagen->scaleDown(1024, 1024);

            // Codificamos la imagen a WebP con calidad del 80%
            $imagenOptimizada = $imagen->encode(new WebpEncoder(quality: 80));

            // Guardamos en el disco de Laravel
            Storage::disk('local')->put($rutaAlmacenada, $imagenOptimizada->toString());

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error optimizando imagen: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw ValidationException::withMessages([
                'imagen' => 'Ocurrió un error al procesar y optimizar la imagen.',
            ]);
        }

        if (! Storage::disk('local')->exists($rutaAlmacenada)) {
            throw ValidationException::withMessages([
                'imagen' => 'No se pudo guardar la imagen en el almacenamiento del servidor.',
            ]);
        }

        return [
            'ruta' => $rutaAlmacenada,
            'mime' => 'image/webp',
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
