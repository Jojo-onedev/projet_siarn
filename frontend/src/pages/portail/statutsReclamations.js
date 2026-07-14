const STATUTS = {
  ouverte: { libelle: 'Ouverte', teinte: 'info' },
  en_traitement: { libelle: 'En traitement', teinte: 'warning' },
  resolue: { libelle: 'Résolue', teinte: 'success' },
  rejetee: { libelle: 'Rejetée', teinte: 'danger' },
};

export const libelleStatutReclamation = (s) => STATUTS[s]?.libelle ?? s;
export const teinteStatutReclamation = (s) => STATUTS[s]?.teinte ?? 'neutre';
