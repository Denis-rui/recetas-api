<?php

namespace App\Actions\Usuarios;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

class GuardarFotoPerfil
{
    private const MIMES_VALIDOS = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * Valida, optimiza a WebP cuando es posible y almacena una fotografía de perfil en el disco público.
     *
     * @throws ValidationException
     */
    public function ejecutar(UploadedFile $archivo): string|false
    {
        if ($archivo->getSize() > 10240 * 1024) {
            throw ValidationException::withMessages([
                'foto_perfil' => 'La foto de perfil no debe superar los 10 MB de tamaño.',
            ]);
        }

        $rutaReal = $archivo->getRealPath();
        $mimeReal = ($rutaReal && is_file($rutaReal)) ? (@mime_content_type($rutaReal) ?: '') : '';

        if (
            extension_loaded('gd')
            && $rutaReal
            && is_file($rutaReal)
            && filesize($rutaReal) > 0
            && in_array($mimeReal, self::MIMES_VALIDOS, true)
        ) {
            $manager = new ImageManager(new Driver);
            $imagen = $manager->decode($rutaReal);
            $imagen->scaleDown(1024, 1024);
            $imagenOptimizada = $imagen->encode(new WebpEncoder(quality: 80));

            $tempFile = tempnam(sys_get_temp_dir(), 'perfil_webp_');
            if ($tempFile !== false) {
                try {
                    file_put_contents($tempFile, $imagenOptimizada->toString());
                    $archivoOptimizado = new UploadedFile(
                        $tempFile,
                        Str::random(40).'.webp',
                        'image/webp',
                        null,
                        true
                    );

                    return Storage::disk('public')->putFileAs(
                        'perfiles',
                        $archivoOptimizado,
                        Str::random(40).'.webp'
                    );
                } finally {
                    if (is_file($tempFile)) {
                        @unlink($tempFile);
                    }
                }
            }
        }

        return $archivo->store('perfiles', 'public');
    }
}

