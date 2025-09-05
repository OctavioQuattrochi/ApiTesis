<?php

namespace App\Mail;

use App\Models\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PresupuestoConfirmacionMail extends Mailable
{
    use Queueable, SerializesModels;

    public $quote;

    /**
     * Create a new message instance.
     */
    public function __construct(Quote $quote)
    {
        $this->quote = $quote;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        Log::channel('presupuestos')->info('Enviando mail de confirmación de presupuesto', [
            'quote_id' => $this->quote->id,
            'user_email' => $this->quote->user->email ?? null,
        ]);

        return $this->subject('Presupuesto listo para tu confirmación')
            ->view('emails.presupuesto_confirmacion');
    }
}
