<?php

namespace App\Services\Notifications;

use App\Mail\NotesPublieesMail;
use App\Models\Alerte;
use App\Models\ProcesVerbal;
use Illuminate\Support\Facades\Mail;

// §7.7 : notifications multicanal (in-app + email) lors de la publication
// des notes. In-app = Alerte (deja utilisee pour le SLA, §9.2) : pas de
// table "notifications" separee, le PRD ne definit qu'Alerte au §10.
class NotificationPublicationService
{
    public function notifierPublication(ProcesVerbal $pv): void
    {
        foreach ($pv->notes()->with('etudiant')->get() as $note) {
            $etudiant = $note->etudiant;
            if (! $etudiant) {
                continue;
            }

            Alerte::create([
                'pv_id' => $pv->id,
                'niveau' => 'info',
                'message' => "Vos notes du module {$pv->code_matiere} ({$pv->semestre}, {$pv->annee_academique}) sont publiees.",
                'destinataire_id' => $etudiant->utilisateur_id,
                'statut_lecture' => false,
                'date_creation' => now(),
            ]);

            // Email uniquement si l'etudiant a active son compte portail (§7.2, E12) -
            // sinon aucune adresse email fiable a associer (Etudiant n'a pas son propre champ email).
            if ($etudiant->utilisateur_id && $etudiant->utilisateur) {
                Mail::to($etudiant->utilisateur->email)->send(new NotesPublieesMail($etudiant, $pv));
            }
        }
    }
}
