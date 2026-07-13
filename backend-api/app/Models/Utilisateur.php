<?php

namespace App\Models;

use App\Enums\RoleUtilisateur;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

// Correspond a la table utilisateurs (infra/db/migrations/0002_utilisateurs_auth.sql).
// Remplace le modele User par defaut de Laravel : SIARN n'utilise pas la table
// "users" standard (schema different, roles, MFA, verrouillage - cf. §7.1, §10).
class Utilisateur extends Model implements AuthenticatableContract
{
    use Authenticatable;
    use HasUuids;

    protected $table = 'utilisateurs';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'mot_de_passe_hash',
        'role',
        'statut_mfa',
        'secret_mfa',
        'actif',
    ];

    protected $hidden = [
        'mot_de_passe_hash',
        'secret_mfa',
    ];

    protected $casts = [
        'role' => RoleUtilisateur::class,
        'statut_mfa' => 'boolean',
        'actif' => 'boolean',
        'tentatives_echec' => 'integer',
        'verrouille_jusqu_a' => 'datetime',
        'dernier_login_a' => 'datetime',
    ];

    public function getAuthPassword(): string
    {
        return $this->mot_de_passe_hash;
    }

    public function estVerrouille(): bool
    {
        return $this->verrouille_jusqu_a !== null && $this->verrouille_jusqu_a->isFuture();
    }
}
