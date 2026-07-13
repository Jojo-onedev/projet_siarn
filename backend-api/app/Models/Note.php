<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Correspond a notes (0004_pv_notes_absences.sql). motif_penalite trace
// explicitement pourquoi une note 00/20 a ete attribuee automatiquement
// (fraude/absence, §7.6), pour la distinguer d'une evaluation normale (§10).
class Note extends Model
{
    use HasUuids;

    protected $table = 'notes';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'etudiant_id',
        'pv_id',
        'valeur',
        'coefficient',
        'credit',
        'etat_validation',
        'motif_penalite',
        'motif_penalite_detail',
        'score_confiance_ocr',
        'cree_par_id',
        'valide_par_id',
    ];

    protected $casts = [
        'valeur' => 'float',
        'coefficient' => 'float',
        'credit' => 'float',
        'score_confiance_ocr' => 'float',
    ];

    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(Etudiant::class);
    }

    public function pv(): BelongsTo
    {
        return $this->belongsTo(ProcesVerbal::class, 'pv_id');
    }

    public function creePar(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'cree_par_id');
    }
}
