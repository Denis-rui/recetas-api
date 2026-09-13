<?php

namespace App\Mail;

use App\Models\RecuperacionPassword;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CodigoRecuperacionMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Crea una nueva instancia del mensaje de recuperación encolable y cifrado.
     *
     * @param  string  $codigo  Código numérico de 6 dígitos (preservando ceros a la izquierda)
     * @param  int  $recuperacionId  Identificador del registro de recuperación en base de datos
     * @param  int  $minutosValidez  Tiempo en minutos de vigencia del código (por defecto 10)
     */
    public function __construct(
        public string $codigo,
        public int $recuperacionId,
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
            with: [
                'codigo' => $this->codigo,
                'minutosValidez' => $this->minutosValidez,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Comprueba la vigencia de la recuperación antes de enviar el correo.
     * Si la recuperación fue invalidada, venció, ya fue verificada/usada o la cuenta
     * cambió de correo o se desactivó, se descarta el envío retornando null.
     */
    public function send($mailer)
    {
        $recuperacion = RecuperacionPassword::find($this->recuperacionId);

        if (! $recuperacion
            || $recuperacion->invalidado_en !== null
            || $recuperacion->codigoHaExpirado()
            || $recuperacion->codigo_verificado_en !== null
            || $recuperacion->usado_en !== null
        ) {
            return null;
        }

        $usuario = User::find($recuperacion->user_id);

        if (! $usuario
            || ! $usuario->estaActivo()
            || strtolower((string) $usuario->email) !== strtolower((string) $recuperacion->email)
        ) {
            return null;
        }

        return parent::send($mailer);
    }
}
