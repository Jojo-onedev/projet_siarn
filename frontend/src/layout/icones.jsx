// Jeu d'icones minimal (traits, sans lib externe) — une icone par entree
// de navigation (layout/navigation.js). Cohérent avec la charte : traits
// arrondis, 20x20, currentColor.
const props = {
  width: 20,
  height: 20,
  viewBox: '0 0 24 24',
  fill: 'none',
  stroke: 'currentColor',
  strokeWidth: 1.8,
  strokeLinecap: 'round',
  strokeLinejoin: 'round',
  'aria-hidden': true,
};

export const ICONES = {
  '/': (
    <svg {...props}><path d="M3 11.5 12 4l9 7.5" /><path d="M5.5 10v9a1 1 0 0 0 1 1H9a1 1 0 0 0 1-1v-4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v4a1 1 0 0 0 1 1h2.5a1 1 0 0 0 1-1v-9" /></svg>
  ),
  '/pv': (
    <svg {...props}><path d="M7 3.5h7l4 4V20a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1Z" /><path d="M14 3.5V8h4" /><path d="M9 13h6M9 16.5h6" /></svg>
  ),
  '/validation': (
    <svg {...props}><path d="M12 3.5 4.5 6.5v5.2c0 4.6 3 7.9 7.5 8.8 4.5-.9 7.5-4.2 7.5-8.8V6.5L12 3.5Z" /><path d="m9 12 2 2 4-4" /></svg>
  ),
  '/referentiels': (
    <svg {...props}><rect x="3.5" y="4" width="7" height="7" rx="1.2" /><rect x="13.5" y="4" width="7" height="7" rx="1.2" /><rect x="3.5" y="13" width="7" height="7" rx="1.2" /><rect x="13.5" y="13" width="7" height="7" rx="1.2" /></svg>
  ),
  '/mes-notes': (
    <svg {...props}><path d="M6 3.5h9l3 3V20a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1Z" /><path d="M8.5 9h7M8.5 12.5h7M8.5 16h4" /></svg>
  ),
  '/mes-reclamations': (
    <svg {...props}><path d="M4 5.5h16v10.5a1 1 0 0 1-1 1H9l-4 4V6.5a1 1 0 0 1 1-1Z" transform="translate(0 -0.5)" /><path d="M8 10h8M8 13h5" /></svg>
  ),
  '/reclamations': (
    <svg {...props}><path d="M4 5h16v10.5a1 1 0 0 1-1 1H9l-4 4V6a1 1 0 0 1 1-1Z" /><path d="M8 9.5h8M8 12.5h5" /></svg>
  ),
  '/tableaux-de-bord': (
    <svg {...props}><path d="M4 20V10M11 20V4M18 20v-7" /></svg>
  ),
  '/audit': (
    <svg {...props}><path d="M12 3.5 4.5 6.5v5.2c0 4.6 3 7.9 7.5 8.8 4.5-.9 7.5-4.2 7.5-8.8V6.5L12 3.5Z" /><path d="M12 8v4.2l3 2" /></svg>
  ),
  '/corpus': (
    <svg {...props}><ellipse cx="12" cy="6" rx="7.5" ry="2.8" /><path d="M4.5 6v12c0 1.5 3.4 2.8 7.5 2.8s7.5-1.3 7.5-2.8V6" /><path d="M4.5 12c0 1.5 3.4 2.8 7.5 2.8s7.5-1.3 7.5-2.8" /></svg>
  ),
  '/modeles-ocr': (
    <svg {...props}><rect x="5" y="5" width="14" height="14" rx="3" /><path d="M9 9h6v6H9zM3 9.5h2M3 14.5h2M19 9.5h2M19 14.5h2M9.5 3v2M14.5 3v2M9.5 19v2M14.5 19v2" /></svg>
  ),
  '/utilisateurs': (
    <svg {...props}><circle cx="9" cy="8" r="3" /><path d="M3.5 20c0-3.3 2.5-5.5 5.5-5.5s5.5 2.2 5.5 5.5" /><circle cx="17.5" cy="8.5" r="2.3" /><path d="M15 14.8c1.4-.4 2.9-.2 3.9.6 1.1.9 1.8 2.5 1.8 4.6" /></svg>
  ),
};

export function IconeNav({ chemin }) {
  return ICONES[chemin] ?? <svg {...props}><circle cx="12" cy="12" r="8" /></svg>;
}
