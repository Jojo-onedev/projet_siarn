<?php

namespace App\Enums;

// Miroir du type Postgres statut_pv (0001_extensions_types.sql). Machine a
// etats du dossier PV — §9.1 du PRD, recopiee telle quelle.
enum StatutPv: string
{
    case Soumis = 'soumis';
    case EnTraitement = 'en_traitement';
    case ErreurExtraction = 'erreur_extraction';
    case EnVerification = 'en_verification';
    case EnValidation = 'en_validation';
    case ComplementRequis = 'complement_requis';
    case Valide = 'valide';
    case Integre = 'integre';
    case Publie = 'publie';
    case Rejete = 'rejete';
    case Archive = 'archive';
}
