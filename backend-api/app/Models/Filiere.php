<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Correspond a filieres (0003_referentiels.sql). chef_departement_id est
// nullable par exigence explicite du PRD (§4, §10) : supporte 3 chefs de
// departement distincts ou 1 responsable academique cumulant les 3 roles,
// sans changement de schema ni de code.
class Filiere extends Model
{
    use HasUuids;

    protected $table = 'filieres';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'nom',
        'code',
        'chef_departement_id',
        'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    public function chefDepartement(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'chef_departement_id');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class);
    }

    public function etudiants(): HasMany
    {
        return $this->hasMany(Etudiant::class);
    }
}
