<?php

namespace App\Mail;

use App\Models\Etudiant;
use App\Models\ProcesVerbal;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

// §7.7 : notification email lors de la publication des notes (§9.1, statut
// 'publie'). MAIL_MAILER=log en dev (voir .env) : le message est ecrit dans
// le journal applicatif plutot qu'envoye reellement, sans infrastructure SMTP.
class NotesPublieesMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Etudiant $etudiant, public readonly ProcesVerbal $pv) {}

    public function build(): self
    {
        return $this->subject("Publication de vos notes - {$this->pv->code_matiere}")
            ->view('emails.notes_publiees');
    }
}
