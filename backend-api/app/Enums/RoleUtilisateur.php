<?php

namespace App\Enums;

// Miroir du type Postgres role_utilisateur (infra/db/migrations/0001_extensions_types.sql).
// Toute modification doit rester synchronisee avec la migration SQL.
enum RoleUtilisateur: string
{
    case AgentScolarite = 'agent_scolarite';
    case Enseignant = 'enseignant';
    case ChefDepartement = 'chef_departement';
    case ResponsableAcademique = 'responsable_academique';
    case Etudiant = 'etudiant';
    case Admin = 'admin';
    case Directeur = 'directeur';

    /**
     * Roles a privileges eleves necessitant le MFA (§13.1 : "MFA obligatoire
     * pour roles a privileges eleves (agents, validateurs, admins)").
     * Validateurs = chef de departement / responsable academique.
     * Directeur inclus : acces global a l'audit et aux tableaux de bord (§9.1 UC-10).
     */
    public function mfaObligatoire(): bool
    {
        return match ($this) {
            self::AgentScolarite,
            self::ChefDepartement,
            self::ResponsableAcademique,
            self::Admin,
            self::Directeur => true,
            self::Enseignant,
            self::Etudiant => false,
        };
    }
}
