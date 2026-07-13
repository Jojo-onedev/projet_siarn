<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Correspond a etudiants (0003_referentiels.sql). §7.2 : referentiel
// etudiants structure par filiere/niveau/annee academique.
class Etudiant extends Model
{
    use HasUuids;

    protected $table = 'etudiants';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'matricule',
        'nom',
        'prenom',
        'filiere_id',
        'niveau',
        'annee_academique',
        'utilisateur_id',
        'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    public function filiere(): BelongsTo
    {
        return $this->belongsTo(Filiere::class);
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class);
    }
}
