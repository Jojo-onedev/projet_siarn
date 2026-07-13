<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Correspond a absences (0004_pv_notes_absences.sql). Utilise par la regle
// §7.6 : cumul >= seuil (configurable) d'absence non justifiee sur un module
// -> penalite automatique 00/20 (App\Services\Notes\ReglesPenaliteService).
class Absence extends Model
{
    use HasUuids;

    protected $table = 'absences';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'etudiant_id',
        'module_id',
        'duree_heures',
        'date',
        'justifiee',
        'declare_par_id',
        'created_at',
    ];

    protected $casts = [
        'duree_heures' => 'float',
        'date' => 'date',
        'justifiee' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(Etudiant::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
