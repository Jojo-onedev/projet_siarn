<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Correspond a decisions (0005_workflow.sql) : trace de la validation
// hierarchique (§7.6, §9.1, E8) - chaque valider/rejeter/complement_requis
// laisse une decision, distincte de historique_transitions_pv (celle-ci
// trace le "quoi" (changement d'etat), Decision trace le "pourquoi/qui a
// decide" avec le motif metier associe.
class Decision extends Model
{
    use HasUuids;

    protected $table = 'decisions';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'pv_id',
        'type_decision',
        'motif',
        'auteur_id',
        'date_decision',
    ];

    protected $casts = [
        'date_decision' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function pv(): BelongsTo
    {
        return $this->belongsTo(ProcesVerbal::class, 'pv_id');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'auteur_id');
    }
}
