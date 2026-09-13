<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CodigoRecuperacionMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Crea una nueva instancia del mensaje de recuperación encolable.
     *
     * @param  string  $codigo  Código numérico de 6 dígitos (preservando ceros a la izquierda)
     * @param  int  $minutosValidez  Tiempo en minutos de vigencia del código (por defecto 10)
     */
    public function __construct(
        public string $codigo,
        public int $minutosValidez = 10
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Código de recuperación de contraseña - ¿Qué Cocinamos?',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.codigo-recuperacion',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
