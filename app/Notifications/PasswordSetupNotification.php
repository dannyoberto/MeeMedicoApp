<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invitación al backoffice: enlace para definir la contraseña de una cuenta nueva
 * (o reenviado si el anterior caducó).
 */
class PasswordSetupNotification extends Notification
{
    public function __construct(public readonly string $url) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $hours = intdiv((int) config('auth.passwords.users.expire'), 60);

        return (new MailMessage)
            ->subject('Tu acceso al backoffice de MeeMedico')
            ->greeting("Hola, {$notifiable->name}")
            ->line('Se creó una cuenta para ti en el backoffice de MeeMedico.')
            ->action('Definir mi contraseña', $this->url)
            ->line("El enlace caduca en {$hours} horas. Si caduca, pide que te lo reenvíen.")
            ->line('Si no esperabas este correo, puedes ignorarlo.');
    }
}
