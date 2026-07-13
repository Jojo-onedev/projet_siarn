<?php

namespace App\Models;

use App\Enums\StatutPv;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Correspond a historique_transitions_pv (0005_workflow.sql) : source de
// verite de la machine a etats du PV (§9.1), distincte du journal_audit
// general (§7.9).
class HistoriqueTransitionPv extends Model
{
    use HasUuids;

    protected $table = 'historique_transitions_pv';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'pv_id',
        'ancien_statut',
        'nouveau_statut',
        'acteur_id',
        'motif',
        'date_heure',
    ];

    protected $casts = [
        'ancien_statut' => StatutPv::class,
        'nouveau_statut' => StatutPv::class,
        'date_heure' => 'datetime',
    ];

    public function pv(): BelongsTo
    {
        return $this->belongsTo(ProcesVerbal::class, 'pv_id');
    }
}
