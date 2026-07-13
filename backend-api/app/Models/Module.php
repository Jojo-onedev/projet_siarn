<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Correspond a modules (0003_referentiels.sql) : precision de conception
// ajoutee en E0 pour donner une cible relationnelle a PV.code_matiere et
// Absence.module_id (non porteurs d'entite dediee dans le PRD §10).
class Module extends Model
{
    use HasUuids;

    protected $table = 'modules';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'code',
        'nom',
        'filiere_id',
        'niveau',
        'semestre',
        'coefficient',
        'credits',
        'actif',
    ];

    protected $casts = [
        'coefficient' => 'float',
        'credits' => 'float',
        'actif' => 'boolean',
    ];

    public function filiere(): BelongsTo
    {
        return $this->belongsTo(Filiere::class);
    }
}
