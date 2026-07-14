// Miroir de App\Enums\StatutPv (backend-api) et de la machine a etats
// MachineEtatsPv::TRANSITIONS - §9.1 du PRD. Purement pour l'affichage
// (libelle + teinte de badge) ; la machine a etats reelle reste
// entierement cote serveur.
export const STATUTS_PV = {
  soumis: { libelle: 'Soumis', teinte: 'neutre' },
  en_traitement: { libelle: 'En traitement', teinte: 'info' },
  erreur_extraction: { libelle: 'Erreur d\'extraction', teinte: 'danger' },
  en_verification: { libelle: 'En vérification', teinte: 'warning' },
  en_validation: { libelle: 'En validation', teinte: 'warning' },
  complement_requis: { libelle: 'Complément requis', teinte: 'danger' },
  valide: { libelle: 'Validé', teinte: 'success' },
  integre: { libelle: 'Intégré', teinte: 'success' },
  publie: { libelle: 'Publié', teinte: 'success' },
  rejete: { libelle: 'Rejeté', teinte: 'danger' },
  archive: { libelle: 'Archivé', teinte: 'neutre' },
};

export function libelleStatut(statut) {
  return STATUTS_PV[statut]?.libelle ?? statut;
}

export function teinteStatut(statut) {
  return STATUTS_PV[statut]?.teinte ?? 'neutre';
}
