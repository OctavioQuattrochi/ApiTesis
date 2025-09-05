<?php
namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Log;

class CustomResetPassword extends ResetPassword
{
    public function toMail($notifiable)
    {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
        $resetUrl = "{$frontendUrl}/reset-password/{$this->token}?email={$notifiable->getEmailForPasswordReset()}";

        Log::channel('usuarios')->info('Enviando mail de restablecimiento de contraseña', [
            'user_id' => $notifiable->id ?? null,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Restablecer contraseña')
            ->line('Recibiste este correo porque se solicitó un restablecimiento de contraseña para tu cuenta.')
            ->action('Restablecer contraseña', $resetUrl)
            ->line('Si no solicitaste el restablecimiento, no es necesario realizar ninguna acción.');
    }
}