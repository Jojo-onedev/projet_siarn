# Guide de test manuel — SIARN

Ce guide sert à tester l'application vous-même, écran par écran, en vous connectant successivement avec chaque persona (rôle) du système. Il complète `PRD_FRONTEND.md` (spécification) et `docs/RECETTE.md` (preuves de test automatisées) — ici, c'est vous qui jouez chaque rôle dans un vrai navigateur.

## Correctifs suite à vos premiers tests (étapes 1 et 2)

- **Corrigé** : après une connexion (mot de passe ou code MFA), vous étiez redirigé vers le tableau de bord même si le MFA n'était pas encore configuré — l'obligation n'apparaissait qu'au premier clic sur un écran métier, ce qui était déroutant. La redirection vers l'activation MFA se fait maintenant **immédiatement** après la connexion pour les rôles concernés.
- **Corrigé (donnée de test)** : le PV que vous aviez importé sous la nouvelle filière « Cybersecurity » était bloqué car aucun chef n'y était assigné. Un chef est maintenant assigné (`chef.smoke...`) — votre PV bloqué en « En validation » devrait être validable normalement si vous réessayez.
- **Corrigé (cause probable trouvée)** : le blocage « je saisis le bon code MFA mais je n'accède pas au tableau de bord » vient très probablement du **jeton de vérification MFA, valide seulement 5 minutes**. Passé ce délai (ex. le temps de retrouver le bon compte dans votre application d'authentification en testant plusieurs rôles), l'écran de code affichait une erreur mais **sans aucun moyen d'en sortir** — vous restiez bloqué à ressaisir un code sur un jeton déjà périmé, ce qui ne pouvait jamais fonctionner. J'ai ajouté un bouton **« Retour à la connexion »** sur cet écran (et un équivalent sur l'écran d'activation MFA), et un message d'erreur explicite quand ce cas se produit. Si vous étiez bloqué : cliquez simplement sur ce nouveau bouton et reconnectez-vous, vous recevrez un code frais.

## 0. Prérequis

- La stack Docker doit tourner : `docker compose ps` doit montrer `postgres`, `backend-api`, `ocr-service` en état `Up`.
- Le serveur de développement frontend doit tourner (`npm run dev` dans `frontend/`) — accessible sur **http://localhost:5173**.
- Aucune inscription libre n'existe (V1, §7.1) : tous les comptes ci-dessous sont déjà créés en base pour vous.

## 1. Comptes de test

Mot de passe **identique pour tous** : `MotDePasse123!`

| Rôle | E-mail | MFA | Particularité |
|---|---|:---:|---|
| Agent de scolarité | `agent.smoke.1783986173@siarn.local` | À configurer au premier login | Compte principal pour importer/vérifier des PV |
| Enseignant | `enseignant.smoke@siarn.local` | Non requis pour ce rôle | Référent du module **GL301** (peut signaler une fraude sur une note de ce module) |
| Chef de département | `chef.smoke.1783986173@siarn.local` | À configurer au premier login | Chef de la filière **Genie Logiciel Smoke** |
| Responsable académique | `responsable.smoke@siarn.local` | À configurer au premier login | Supervise **toutes** les filières (pas de filière assignée nécessaire) |
| Étudiant | `etudiant.smoke.1783986173@siarn.local` | Non requis pour ce rôle | Matricule **MAT001**, Kaboré Awa, filière Genie Logiciel Smoke |
| Administrateur | `admin.smoke@siarn.local` | À configurer au premier login | Gère utilisateurs/corpus/modèles OCR |
| Directeur | `directeur.smoke@siarn.local` | À configurer au premier login | Audit + tableaux de bord consolidés |

**« À configurer au premier login »** : à la première connexion, vous serez automatiquement redirigé vers l'écran d'activation MFA. Une clé secrète (texte, à copier) s'affiche — **il n'y a pas de QR code** (choix technique assumé du backend, otpauth construit manuellement). Ajoutez un compte **manuellement** dans une application d'authentification (Google Authenticator, Authy, 2FAS, ou une extension de navigateur type "Authenticator"), en collant la clé secrète, avant de saisir le code à 6 chiffres généré. Aux connexions suivantes, cette même application vous redonnera le code à entrer.

## 2. Fichiers d'exemple à utiliser

Dans `exemples-test/` à la racine du projet :

| Fichier | Usage |
|---|---|
| `pv_exemple_1.jpg`, `pv_exemple_2.jpg` | Import de PV (image de test générée, texte non manuscrit — l'OCR donnera un résultat peu fiable, **c'est volontaire** : ça vous permet de tester concrètement l'écran de correction humaine) |
| `etudiants_exemple.csv` | Import de liste d'étudiants (filière `GLS986172` déjà existante) |

## 3. Parcours de test recommandé

Suivez cet ordre : chaque étape prépare les données nécessaires à la suivante (comme un vrai cycle de délibération).

### Étape 1 — Agent de scolarité

Connectez-vous avec `agent.smoke.1783986173@siarn.local`, configurez le MFA.

- [ ] **Référentiels** (`/referentiels`) : consultez la filière « Genie Logiciel Smoke » et le module GL301 déjà créés.
- [ ] **Référentiels → Étudiants** : importez `etudiants_exemple.csv`, vérifiez le compte-rendu (créés/mis à jour/erreurs) — l'import CSV ne crée jamais de compte de connexion.
- [ ] Créez un étudiant manuellement, cochez **« Créer aussi un accès au portail étudiant »** : matricule et mot de passe sont pré-générés (boutons « Générer »), l'e-mail est suggéré depuis le nom/prénom. Notez le mot de passe affiché dans le récapitulatif final (il ne sera plus jamais montré) — vous vous en servirez à l'étape 4 pour tester la connexion de ce nouvel étudiant.
- [ ] *Optionnel, pour voir le formulaire* : créez une nouvelle filière/un nouveau module. ⚠️ **N'utilisez pas cette nouvelle filière pour l'import de PV ci-dessous** — un agent (non-admin) ne peut pas assigner de chef de département à la création (§F2, seul l'admin le peut), donc aucun chef ne pourra valider un PV qui y serait rattaché. Utilisez toujours **« Genie Logiciel Smoke » (code `GLS986172`)** pour la suite du parcours.
- [ ] **Procès-verbaux** (`/pv`) : cliquez « Importer des PV », sélectionnez `pv_exemple_1.jpg`, choisissez la filière **Genie Logiciel Smoke**, le module GL301, un semestre/année. Après import, le PV apparaît en statut **« En vérification »**.
- [ ] Ouvrez le PV importé : l'image prétraitée s'affiche avec des zones surlignées cliquables. Corrigez les 3 champs (en-tête, tableau de notes, signatures) — testez le survol/clic d'une zone pour voir le champ correspondant se surligner.
- [ ] Dans la section **Notes**, ajoutez une note pour un étudiant (ex. MAT002 importé plus haut).
- [ ] Une fois tous les champs corrigés, le statut passe automatiquement à **« En validation »**.

### Étape 2 — Chef de département (ou Responsable académique)

Connectez-vous avec `chef.smoke.1783986173@siarn.local`, configurez le MFA.

- [ ] **Validation** (`/validation`) : le PV importé à l'étape 1 doit y apparaître (filtré sur « en validation »).
- [ ] Ouvrez-le, choisissez **Valider**. Le statut passe à **« Intégré »**.
- [ ] Vérifiez que **Référentiels/Tableaux de bord** ne montrent que la filière dont ce compte est chef (portée vérifiée côté serveur).

⚠️ Si vous obtenez *« Vous n'êtes pas le chef de département de cette filière »* : le PV a été importé sous une filière dont ce compte n'est pas le chef (voir l'avertissement de l'étape 1). Deux solutions : reprendre l'étape 1 avec la bonne filière, **ou** vous connecter avec `responsable.smoke@siarn.local` qui peut valider n'importe quelle filière (voir ci-dessous).

*Optionnel* : reconnectez-vous en `responsable.smoke@siarn.local` pour comparer — ce rôle voit **toutes** les filières sans restriction.

### Étape 3 — Agent de scolarité (publication)

Reconnectez-vous avec `agent.smoke.1783986173@siarn.local`.

- [ ] Ouvrez le PV (statut « Intégré ») → section **Publication** → confirmez. Statut final : **« Publié »**.

### Étape 4 — Étudiant

Connectez-vous avec `etudiant.smoke.1783986173@siarn.local` (pas de MFA à configurer).

- [ ] **Mes notes** (`/mes-notes`) : la note du PV publié doit apparaître.
- [ ] Pour vérifier la règle « aucune note visible sans publication » : reconnectez-vous en agent, importez `pv_exemple_2.jpg` en le laissant volontairement **sans le publier** (arrêtez-vous après la correction des champs, sans valider ni publier), saisissez une note pour l'étudiant MAT001, puis reconnectez-vous en étudiant — cette nouvelle note ne doit **pas** apparaître dans « Mes notes » tant que ce PV n'est pas publié.
- [ ] **Mes réclamations** (`/mes-reclamations`) : créez une réclamation.

### Étape 5 — Agent de scolarité (traitement réclamation)

- [ ] **Réclamations** (`/reclamations`) : trouvez la réclamation de l'étudiant, répondez-y (changez son statut).
- [ ] Reconnectez-vous en étudiant pour vérifier que la réponse est visible dans « Mes réclamations ».

### Étape 6 — Pilotage (Chef / Responsable / Directeur)

- [ ] **Tableaux de bord** (`/tableaux-de-bord`) : vérifiez les statistiques (total PV, délai moyen, répartition par statut), testez l'export CSV.
- [ ] Avec `directeur.smoke@siarn.local` (configurez son MFA) : mêmes tableaux de bord + accès à **Journal d'audit** (`/audit`) — retrouvez-y les actions effectuées aux étapes précédentes (import, transitions, réclamation…), avec filtres par action/cible/date.

### Étape 7 — Administrateur

Connectez-vous avec `admin.smoke@siarn.local`, configurez le MFA.

- [ ] **Utilisateurs** (`/utilisateurs`) : consultez la liste, créez un nouveau compte.
- [ ] **Modèles OCR** (`/modeles-ocr`) : consultez le modèle actif (CER/WER) — notez l'avertissement sur la non-représentativité de ces chiffres.
- [ ] **Corpus OCR** (`/corpus`) : des documents y apparaissent déjà (la boucle de rétroaction a exporté automatiquement les corrections faites à l'étape 1). Ouvrez-en un, ajoutez une annotation. Testez « Répartir train/val/test ».

### Étape 8 — Enseignant *(limitation connue, voir §4)*

Connectez-vous avec `enseignant.smoke@siarn.local` — vous constaterez que le tableau de bord est **vide**, sans section « Procès-verbaux » dans le menu. C'est normal, voir ci-dessous.

## 4. Limitations connues (pas des bugs à signaler, déjà documentées dans le code)

- **Enseignant** : l'API backend (`routes/api.php`) n'autorise ce rôle que sur `POST /notes/{id}/signaler-fraude` — aucune route ne lui permet de *lister* les PV ou notes pour y trouver l'identifiant nécessaire. Résultat : cette persona n'a aujourd'hui aucun écran fonctionnel dans le frontend. C'est un écart RBAC côté backend (le §5 du PRD prévoit « Vérifier son propre PV » pour ce rôle), volontairement non corrigé sans votre feu vert — dites-moi si vous voulez que je l'ajoute.
- **Corpus OCR** : pas d'aperçu image lors de l'annotation (contrairement à l'écran de vérification PV) — coordonnées de zone saisies en chiffres.
- **Export tableaux de bord** : CSV uniquement, pas de PDF/Excel.
- **Modèle OCR** : le CER/WER affiché vient d'un corpus synthétique (§17 du PRD, `docs/RECETTE.md`) — pas représentatif d'un usage réel.
- **Session** : pas de rafraîchissement automatique du jeton — au bout d'1h, il faudra vous reconnecter (pas de bug, juste un aller-retour à prévoir si un test traîne en longueur).

## 5. Si quelque chose ne va pas

Notez : l'écran concerné, le rôle utilisé, et si possible le message d'erreur affiché (ou ouvrez la console navigateur, F12). Dites-le-moi et je corrige.
