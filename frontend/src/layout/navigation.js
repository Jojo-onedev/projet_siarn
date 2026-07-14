// Structure de navigation — mapping direct vers la roadmap frontend
// (PRD_FRONTEND.md §9, F1-F8). `epic` indique dans quel epic l'ecran reel
// sera construit ; tant que l'epic n'est pas livre, la route pointe vers
// un ecran "a venir" honnete plutot qu'une fonctionnalite factice.
export const NAVIGATION = [
  { label: 'Tableau de bord', to: '/', epic: 'F1', implemente: true, roles: null },
  {
    label: 'Procès-verbaux',
    to: '/pv',
    epic: 'F3/F4',
    implemente: true,
    // Note : GET /pv et /pv/{id} n'incluent pas 'enseignant' cote backend
    // (routes/api.php) - ce role a son propre ecran scope a ses modules
    // ("Mes modules" ci-dessous), pas d'acces a la liste complete des PV.
    roles: ['agent_scolarite', 'chef_departement', 'responsable_academique', 'directeur', 'admin'],
  },
  {
    label: 'Mes modules',
    to: '/mes-modules',
    epic: 'suite-tests',
    implemente: true,
    roles: ['enseignant'],
  },
  {
    label: 'Validation',
    to: '/validation',
    epic: 'F5',
    implemente: true,
    roles: ['chef_departement', 'responsable_academique'],
  },
  {
    label: 'Absences',
    to: '/absences',
    epic: 'suite-tests',
    implemente: true,
    roles: ['agent_scolarite', 'enseignant', 'admin'],
  },
  {
    label: 'Référentiels',
    to: '/referentiels',
    epic: 'F2',
    implemente: true,
    roles: ['agent_scolarite', 'chef_departement', 'responsable_academique', 'directeur', 'admin'],
  },
  {
    label: 'Mes notes',
    to: '/mes-notes',
    epic: 'F6',
    implemente: true,
    roles: ['etudiant'],
  },
  {
    label: 'Mes réclamations',
    to: '/mes-reclamations',
    epic: 'F6',
    implemente: true,
    roles: ['etudiant'],
  },
  {
    label: 'Réclamations',
    to: '/reclamations',
    epic: 'F6',
    implemente: true,
    roles: ['agent_scolarite', 'chef_departement', 'responsable_academique', 'admin'],
  },
  {
    label: 'Tableaux de bord',
    to: '/tableaux-de-bord',
    epic: 'F7',
    implemente: true,
    roles: ['chef_departement', 'responsable_academique', 'directeur'],
  },
  {
    label: 'Journal d\'audit',
    to: '/audit',
    epic: 'F7',
    implemente: true,
    roles: ['admin', 'directeur'],
  },
  {
    label: 'Corpus OCR',
    to: '/corpus',
    epic: 'F8',
    implemente: true,
    roles: ['agent_scolarite', 'admin'],
  },
  {
    label: 'Modèles OCR',
    to: '/modeles-ocr',
    epic: 'F8',
    implemente: true,
    roles: ['admin'],
  },
  {
    label: 'Utilisateurs',
    to: '/utilisateurs',
    epic: 'F8',
    implemente: true,
    roles: ['admin'],
  },
];

export function navigationPourRole(role) {
  return NAVIGATION.filter((item) => !item.roles || item.roles.includes(role));
}
