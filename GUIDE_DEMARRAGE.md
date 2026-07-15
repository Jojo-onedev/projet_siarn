# Guide de démarrage — reprendre le projet SIARN sur une nouvelle machine

Ce guide explique, étape par étape, comment récupérer le projet depuis GitHub et le faire tourner sur votre ordinateur pour pouvoir le tester. Il ne contient pas d'explications de code — seulement le déroulé des opérations à effectuer, dans l'ordre. Une fois le projet démarré, référez-vous à `GUIDE_TEST_MANUEL.md` pour le tester écran par écran.

## 0. Où en est le projet

Le développement est terminé pour cette phase : le backend (la logique métier et la base de données) et le frontend (l'interface visible dans le navigateur) sont tous les deux fonctionnels et reliés entre eux. Tout est déjà envoyé sur GitHub. Il ne reste qu'à faire tourner le projet sur votre machine.

## 1. Ce qu'il faut installer avant de commencer

Quatre logiciels sont nécessaires. S'ils sont déjà installés, passez à l'étape 2.

- **Git** — pour récupérer le projet depuis GitHub.
- **Docker Desktop** — le projet est découpé en plusieurs services (base de données, backend, service de reconnaissance de texte) qui tournent chacun dans leur propre conteneur isolé ; Docker Desktop est l'outil qui les fait tourner tous ensemble. Une fois installé, assurez-vous qu'il utilise le mode **WSL2** (c'est le réglage recommandé par défaut sur Windows) plutôt que l'ancien mode Hyper-V — c'est nettement plus rapide.
- **Node.js** (version 20 ou plus récente) — nécessaire pour faire tourner la partie interface visuelle (le frontend) en dehors de Docker, ce qui est plus rapide pendant le développement.
- Une **application d'authentification** sur votre téléphone (Google Authenticator, Microsoft Authenticator, Authy, 2FAS...) — le projet impose une double authentification pour la plupart des rôles, vous en aurez besoin dès la première connexion.

## 2. ⚠️ Un point important avant de cloner : où placer le dossier

**Ne placez surtout pas le projet dans un dossier synchronisé par OneDrive, Google Drive, Dropbox ou équivalent** (typiquement, évitez `Bureau`, `Documents` ou `Téléchargements` si l'un de ces dossiers est redirigé vers un service de synchronisation cloud — c'est un réglage courant sur Windows).

La raison : le projet contient énormément de petits fichiers (les dépendances du backend), et Docker doit constamment y accéder. Si ces fichiers sont en plus surveillés en permanence par un service de synchronisation cloud, chaque accès devient extrêmement lent — nous l'avons constaté directement pendant le développement : une simple page qui devrait s'afficher en une fraction de seconde peut alors prendre 20 secondes ou plus.

Choisissez plutôt un dossier local simple, par exemple `C:\Projets\` ou `C:\Dev\`, clairement en dehors de toute synchronisation cloud.

## 3. Récupérer le projet depuis GitHub

Ouvrez un terminal dans le dossier choisi à l'étape précédente, et récupérez le projet à l'adresse suivante :

`https://github.com/Jojo-onedev/projet_siarn.git`

Cela crée un dossier `projet_siarn` contenant tout le code. Placez-vous ensuite à l'intérieur de ce dossier pour la suite des opérations.

## 4. Configurer le backend

Le fichier qui contient les réglages sensibles du backend (mots de passe, clés de sécurité) n'est volontairement **pas** inclus dans le projet GitHub — c'est une pratique de sécurité standard. Il faut le créer localement :

1. Dans le dossier `backend-api/`, il existe un fichier modèle nommé `.env.example`. Faites-en une copie et renommez cette copie en `.env` (toujours dans `backend-api/`).
2. Deux valeurs dans ce nouveau fichier `.env` doivent être remplies avant de démarrer : `APP_KEY` et `JWT_SECRET` (elles sont vides dans le modèle) — deux clés de sécurité **volontairement distinctes** (la compromission de l'une ne doit pas affecter l'autre). Une fois les conteneurs démarrés (étape suivante) :
   - `APP_KEY` se génère et s'écrit automatiquement dans le `.env` via une commande Laravel dédiée (`key:generate`).
   - `JWT_SECRET` n'a pas d'équivalent automatique : la même commande Laravel peut néanmoins servir à produire une seconde valeur aléatoire (option `--show`, qui l'affiche sans l'écrire) — copiez ce qu'elle affiche dans `JWT_SECRET` à la main.

## 5. Démarrer les services (Docker)

Toujours à la racine du projet (`projet_siarn/`), lancez le démarrage de l'ensemble des services. Cette étape construit les images Docker (télécharge et prépare tout ce dont chaque service a besoin) puis les démarre — la toute première fois, cela peut prendre plusieurs minutes selon la vitesse de la connexion internet.

Une fois le démarrage terminé, complétez les deux clés de sécurité laissées vides à l'étape précédente : générez `APP_KEY` via la commande Laravel dédiée (elle s'écrit automatiquement dans le `.env`), puis générez une seconde valeur aléatoire avec la même commande en mode « affichage seul » et copiez-la manuellement dans `JWT_SECRET`.

Vérifiez ensuite que les trois services principaux tournent bien (base de données, backend, service de reconnaissance de texte) — une commande Docker dédiée permet de lister les conteneurs actifs et leur état.

## 6. Préparer la base de données

La base de données démarre vide : il faut y créer les tables (les migrations) puis y insérer un tout premier compte administrateur (le seul moyen d'entrer dans l'application la première fois, puisqu'il n'existe pas d'inscription libre — c'est volontaire, seul un administrateur peut créer de nouveaux comptes).

Deux commandes Laravel (le framework du backend) s'exécutent à l'intérieur du conteneur backend : l'une crée les tables, l'autre insère le compte administrateur initial. Les identifiants de ce premier compte sont :

- E-mail : `admin@siarn.local`
- Mot de passe : `ChangeMoi123!`

**Changez ce mot de passe dès la première connexion** (un bouton dédié existe dans l'application une fois connecté) — il est volontairement simple et documenté publiquement, donc à ne jamais garder tel quel au-delà d'un usage de test local.

## 7. Préparer et démarrer le frontend

Le frontend (l'interface visuelle) tourne mieux directement sur votre machine plutôt que dans Docker pendant le développement, pour des rechargements plus rapides à chaque modification.

1. Placez-vous dans le dossier `frontend/`.
2. Installez les dépendances du frontend (une commande npm dédiée). **Sur Windows, un bug connu de npm empêche parfois cette installation de se terminer correctement** (un composant natif nécessaire à l'outil de développement ne s'installe pas). Si après cette installation le démarrage du frontend (étape suivante) affiche une erreur mentionnant un « binding » introuvable : relancez l'installation une seconde fois, cela suffit généralement à résoudre le problème.
3. Créez un petit fichier de configuration à la racine de `frontend/` indiquant au frontend où joindre le backend (une seule ligne, l'adresse du backend démarré à l'étape 5).
4. Démarrez le frontend en mode développement.

Une fois démarré, le frontend est accessible dans un navigateur à l'adresse indiquée dans le terminal (normalement `http://localhost:5173`).

## 8. Première connexion

Ouvrez cette adresse dans votre navigateur. Vous devriez voir l'écran de connexion de SIARN. Connectez-vous avec le compte administrateur créé à l'étape 6.

Comme ce rôle exige la double authentification, vous serez immédiatement redirigé vers un écran d'activation : un **QR code** s'affiche, à scanner avec l'application d'authentification installée sur votre téléphone (étape 1). Une fois le compte ajouté dans l'application, entrez le code à 6 chiffres qu'elle affiche pour terminer l'activation.

Vous arrivez ensuite sur le tableau de bord administrateur.

## 9. Recréer des données de démonstration

Le fichier `GUIDE_TEST_MANUEL.md` (à lire ensuite) décrit un parcours de test avec des comptes précis (un agent de scolarité, un chef de département, un étudiant, etc.). **Ces comptes n'existent pas encore sur votre machine** — ils avaient été créés uniquement dans l'environnement de test utilisé pendant le développement, pas dans le projet lui-même.

Pour reproduire une situation similaire et suivre ce guide de test, connectée en administrateur :

1. Dans l'écran **Utilisateurs**, créez un compte pour chaque rôle que vous voulez tester (agent de scolarité, chef de département, étudiant, etc.) — un mot de passe temporaire est à définir à la création, à communiquer ensuite si plusieurs personnes doivent s'en servir.
2. Dans l'écran **Référentiels**, créez au moins une filière, un module rattaché à cette filière, et un étudiant (avec la case « créer aussi un accès au portail étudiant » cochée si vous voulez tester la connexion côté étudiant).
3. Vous pouvez ensuite suivre `GUIDE_TEST_MANUEL.md` en adaptant les adresses e-mail utilisées à celles que vous venez de créer.

## 10. (Optionnel) Avoir un vrai modèle de reconnaissance de texte actif

Pour que l'import d'un procès-verbal scanné produise une extraction exploitable (plutôt que de rester bloqué en traitement), un modèle de reconnaissance de texte doit être actif. `docs/DEPLOIEMENT.md` explique comment lancer l'entraînement d'un modèle (une commande Docker dédiée, distincte du démarrage normal). Ce n'est nécessaire que si vous voulez tester l'import de PV de bout en bout — les autres écrans fonctionnent sans cette étape.

## 11. Le reste de la documentation

Une fois le projet démarré, ces documents (à la racine du projet et dans `docs/`) donnent le reste du contexte :

- `GUIDE_TEST_MANUEL.md` — parcours de test détaillé par rôle.
- `docs/DEPLOIEMENT.md` — détails techniques de déploiement (migrations, entraînement du modèle).
- `docs/ARCHITECTURE.md` — comment le projet est construit.
- `docs/API.md` — la liste des fonctionnalités exposées par le backend.
- `docs/RECETTE.md` — l'état des critères d'acceptation du projet.
- `PRD_SIARN.md` et `PRD_FRONTEND.md` — le cahier des charges d'origine.

## 12. En cas de blocage

- **Le frontend refuse de démarrer avec une erreur sur un « binding » manquant** : voir l'étape 7, relancez l'installation des dépendances une seconde fois.
- **Tout est très lent** (les pages mettent plusieurs secondes à s'afficher) : le projet est probablement placé dans un dossier synchronisé par le cloud (voir l'étape 2) — déplacez-le dans un dossier local simple.
- **Un port est déjà utilisé** (erreur au démarrage de Docker mentionnant qu'un port est déjà pris) : un autre programme sur la machine utilise déjà ce port (souvent un PostgreSQL déjà installé localement, ou un autre projet). Fermez ce programme, ou signalez-le pour qu'on adapte la configuration.
- **Impossible de se connecter, même avec les bons identifiants** : vérifiez que les trois services Docker (base de données, backend, service de reconnaissance de texte) sont bien démarrés (étape 5).

Pour toute autre situation bloquante, notez l'écran concerné et le message d'erreur exact affiché (idéalement une capture d'écran), afin de pouvoir la transmettre pour analyse.
