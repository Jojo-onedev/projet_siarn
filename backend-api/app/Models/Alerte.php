<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Correspond a alertes (0005_workflow.sql) - §9.2 : moteur d'alerte
// (SLA, blocages). destinataire_id nullable = alerte "globale" (ex: pour
// les admins), sinon ciblee sur un utilisateur precis.
class Alerte extends Model
{
    use HasUuids;

    protected $table = 'alertes';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'pv_id',
        'niveau',
        'message',
        'destinataire_id',
        'statut_lecture',
        'date_creation',
    ];

    protected $casts = [
        'statut_lecture' => 'boolean',
        'date_creation' => 'datetime',
    ];

    public function pv(): BelongsTo
    {
        return $this->belongsTo(ProcesVerbal::class, 'pv_id');
    }
}
