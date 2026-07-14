import './ui.css';

// Table generique — colonnes = [{cle, entete, rendu?(ligne)}]. Empile en
// cartes sous 640px plutot que de forcer un scroll horizontal (§responsive).
export function Tableau({ colonnes, lignes, cleLigne, surLigneClic, vide = 'Aucun element.' }) {
  if (!lignes.length) {
    return <p className="tableau__vide">{vide}</p>;
  }

  return (
    <div className="tableau">
      <table className="tableau__desktop">
        <thead>
          <tr>
            {colonnes.map((c) => <th key={c.cle}>{c.entete}</th>)}
          </tr>
        </thead>
        <tbody>
          {lignes.map((ligne) => (
            <tr key={ligne[cleLigne]} onClick={surLigneClic ? () => surLigneClic(ligne) : undefined} className={surLigneClic ? 'tableau__ligne--clic' : ''}>
              {colonnes.map((c) => <td key={c.cle}>{c.rendu ? c.rendu(ligne) : ligne[c.cle]}</td>)}
            </tr>
          ))}
        </tbody>
      </table>

      <div className="tableau__cartes">
        {lignes.map((ligne) => (
          <div key={ligne[cleLigne]} className="tableau__carte" onClick={surLigneClic ? () => surLigneClic(ligne) : undefined}>
            {colonnes.map((c) => (
              <div key={c.cle} className="tableau__carte-champ">
                <span className="tableau__carte-label">{c.entete}</span>
                <span>{c.rendu ? c.rendu(ligne) : ligne[c.cle]}</span>
              </div>
            ))}
          </div>
        ))}
      </div>
    </div>
  );
}
