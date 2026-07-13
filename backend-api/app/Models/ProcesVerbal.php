<?php

namespace App\Models;

use App\Enums\StatutPv;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Correspond a proces_verbaux (0004_pv_notes_absences.sql, 0008_pv_pretraitement.sql).
// Le statut ne doit JAMAIS etre modifie directement ($pv->statut = ...) :
// passer par App\StateMachines\MachineEtatsPv::transitionner() pour garantir
// la validite de la transition et sa journalisation (§9.1).
class ProcesVerbal extends Model
{
    use HasUuids;

    protected $table = 'proces_verbaux';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'nom_fichier',
        'chemin_fichier',
        'hash_fichier',
        'code_matiere',
        'module_id',
        'filiere_id',
        'semestre',
        'annee_academique',
        'date_scan',
        'statut',
        'depose_par_id',
        'modele_ocr_id',
        'type_gabarit',
        'chemin_image_pretraitee',
        'zones_segmentees',
        'champs_extraits',
    ];

    protected $casts = [
        'statut' => StatutPv::class,
        'date_scan' => 'datetime',
        'zones_segmentees' => 'array',
        'champs_extraits' => 'array',
    ];

    public function modeleOcr(): BelongsTo
    {
        return $this->belongsTo(ModeleOcr::class, 'modele_ocr_id');
    }

    public function filiere(): BelongsTo
    {
        return $this->belongsTo(Filiere::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function deposePar(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'depose_par_id');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(HistoriqueTransitionPv::class, 'pv_id');
    }
}
