<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Correspond a reclamations (0011_reclamations.sql) - §7.7, UC-07. Entite
// ajoutee en precision de conception (absente du tableau §10, comme
// "modules" en E0) mais explicitement requise par le PRD.
class Reclamation extends Model
{
    use HasUuids;

    protected $table = 'reclamations';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'etudiant_id',
        'note_id',
        'motif',
        'statut',
        'reponse',
        'traite_par_id',
        'date_creation',
        'date_traitement',
    ];

    protected $casts = [
        'date_creation' => 'datetime',
        'date_traitement' => 'datetime',
    ];

    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(Etudiant::class);
    }

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }

    public function traitePar(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'traite_par_id');
    }
}
