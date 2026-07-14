// Generateurs cote client pour le formulaire "Nouvel etudiant" - matricule
// et mot de passe proposes a titre pratique (l'agent peut toujours les
// modifier), l'unicite finale reste verifiee cote serveur (§7.2).

export function genererMatricule() {
  const suffixe = Math.floor(100000 + Math.random() * 900000);
  return `MAT${suffixe}`;
}

const CARACTERES_MOT_DE_PASSE = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%';

export function genererMotDePasse(longueur = 16) {
  const valeurs = new Uint32Array(longueur);
  crypto.getRandomValues(valeurs);
  return Array.from(valeurs, (v) => CARACTERES_MOT_DE_PASSE[v % CARACTERES_MOT_DE_PASSE.length]).join('');
}

const PLAGE_DIACRITIQUES = new RegExp('[̀-ͯ]', 'g');

function retirerAccents(texte) {
  return texte.normalize('NFD').replace(PLAGE_DIACRITIQUES, '');
}

function slugifier(texte) {
  return retirerAccents(texte)
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '')
    .trim();
}

export function suggererEmail(prenom, nom, domaine = 'siarn.local') {
  const p = slugifier(prenom || '');
  const n = slugifier(nom || '');
  if (!p && !n) return '';
  return `${[p, n].filter(Boolean).join('.')}@${domaine}`;
}
