export const NIVEAUX = ['L1', 'L2', 'L3', 'M1', 'M2'];
export const SEMESTRES = ['S1', 'S2', 'S3', 'S4', 'S5', 'S6'];

export function anneesAcademiques(nombre = 5) {
  const anneeDebut = new Date().getFullYear();
  return Array.from({ length: nombre }, (_, i) => {
    const debut = anneeDebut - 1 + i;
    return `${debut}-${debut + 1}`;
  });
}
