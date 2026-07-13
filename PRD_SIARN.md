# PRD — SIARN
### Système Intelligent d'Automatisation du Report de Notes basé sur l'OCR

**Version :** 1.0 — dérivé du Cahier des charges v2
**Statut :** Prêt pour conception / développement
**Destinataire :** Agent IA de développement (et équipe technique)
**Type de document :** Product Requirements Document (PRD) technique et fonctionnel

---

## 0. Comment utiliser ce document (instructions pour l'agent IA)

Ce PRD est la source de vérité pour concevoir et développer SIARN. Règles de travail :

1. **Ne jamais casser le périmètre défini en section 3.** Toute fonctionnalité hors périmètre (mobile, paiement en ligne, interconnexion tierce, reconnaissance manuscrite cursive) doit être refusée ou reportée en "évolution future", même si elle semble utile.
2. **Le moteur OCR entraîné sur mesure (section 8) est le cœur du projet.** Ne jamais le remplacer silencieusement par un simple appel à Tesseract "out of the box" — le pipeline d'entraînement/évaluation fait partie du livrable, pas juste l'inférence.
3. **Toute décision impliquant les notes des étudiants doit rester "Human-in-the-loop".** L'IA ne valide jamais une note de façon autonome et définitive ; elle propose, l'humain confirme.
4. **Respecter la matrice RBAC (section 5)** à chaque endpoint API et composant d'interface : vérification des droits côté serveur systématique.
5. **Construire par incréments testables** en suivant les Épics de la section 13 (Roadmap), dans l'ordre, sauf dépendance technique contraire justifiée.
6. **Documenter les écarts** : si une contrainte technique impose de dévier d'une spécification, l'agent doit le signaler explicitement dans le code (commentaire / ADR) plutôt que de dévier silencieusement.
7. **Toute action sensible (validation, rejet, modification de note, connexion) doit être journalisée** dans le journal d'audit dès sa première implémentation — ce n'est pas une fonctionnalité "à ajouter plus tard".

---

## 1. Résumé exécutif

SIARN est une plateforme web destinée aux services de scolarité universitaires (Burkina Faso). Elle numérise et digitalise le report des notes académiques : un procès-verbal (PV) papier scanné est traité par un moteur OCR **entraîné spécifiquement** sur les gabarits documentaires de l'établissement, puis vérifié par un agent humain ("Human-in-the-loop"), validé selon un circuit hiérarchique paramétrable, intégré dans la base académique et publié aux étudiants.

Le projet remplace un processus 100 % manuel (source de lenteurs, d'erreurs de transcription et de perte de traçabilité) par un pipeline semi-automatisé fiable, sécurisé et audité.

**Trois piliers du produit :**
- **Extraction intelligente** : vision par ordinateur (OpenCV) + OCR entraîné sur mesure (Tesseract fine-tuné).
- **Fiabilisation humaine** : interface de vérification comparative avec surlignage des zones à faible confiance.
- **Gouvernance et traçabilité** : workflow paramétrable, RBAC strict, piste d'audit inviolable.

---

## 2. Contexte et problématique

Le report de notes se fait aujourd'hui via saisie manuelle des PV papier vers le système de scolarité, ce qui génère :
- des **délais** importants entre la fin des examens et la publication des résultats ;
- des **erreurs de transcription** (inversions, fautes de frappe, omissions) ;
- une **traçabilité faible** (recherche des PV originaux longue et incertaine en cas de contestation) ;
- une **absence de pilotage en temps réel** de l'avancement des délibérations.

**Question centrale à résoudre par le produit :**
Comment digitaliser l'extraction des notes à partir de documents physiques grâce à un moteur OCR spécifiquement entraîné, tout en garantissant fiabilité (contrôle humain), sécurité et traçabilité (audit inaltérable) ?

---

## 3. Objectifs et périmètre

### 3.1 Objectif général
Concevoir une plateforme web qui automatise l'extraction, la vérification et l'intégration des notes académiques à partir de PV scannés, via un moteur OCR entraîné sur mesure, afin de fiabiliser, sécuriser et accélérer la délibération universitaire.

### 3.2 Objectifs spécifiques (= critères de succès produit)
| # | Objectif | Indicateur de succès |
|---|---|---|
| O1 | Dématérialiser et centraliser le flux documentaire | 100 % des PV traités via la plateforme, zéro dossier papier hors circuit |
| O2 | Entraîner et évaluer un moteur OCR spécialisé | CER < 3 % sur les champs numériques (notes, matricules) sur jeu de test indépendant |
| O3 | Fiabiliser via Human-in-the-loop | 0 note publiée sans validation humaine explicite |
| O4 | Orchestrer un workflow paramétrable avec SLA | Traçabilité complète de chaque transition d'état |
| O5 | Sécuriser les données sensibles | MFA actif, chiffrement au repos/transit, logs inviolables |
| O6 | Fournir des outils de pilotage | Tableaux de bord temps réel par filière/promotion |

### 3.3 Périmètre — Inclus
- Gestion des comptes et rôles utilisateurs (RBAC, MFA)
- Numérisation (scanner/imprimante externe) + import + prétraitement d'image (OpenCV)
- Constitution, annotation et gestion d'un corpus de PV pour l'entraînement OCR
- Entraînement (fine-tuning) et évaluation d'un moteur OCR spécialisé (Tesseract)
- Interface de vérification Human-in-the-loop
- Calcul automatique des moyennes + règles de pénalité (fraude, absence)
- Workflow de validation hiérarchique paramétrable
- Tableaux de bord et rapports (export PDF/Excel)
- Journal d'audit inviolable

### 3.4 Périmètre — Exclus (hors V1, évolutions futures possibles)
- Reconnaissance d'écriture manuscrite cursive complexe
- Système de détection de fraude par IA avancée (au-delà des règles définies)
- Interconnexion directe avec des logiciels tiers
- Édition physique sécurisée de diplômes
- Application mobile dédiée
- Paiement en ligne des frais annexes

> **Règle pour l'agent IA :** si une demande future touche l'un de ces points, la traiter comme une évolution hors périmètre V1, à documenter séparément — ne pas l'implémenter en silence dans le cadre de ce PRD.

---

## 4. Personas / Acteurs

| Acteur | Description | Objectif principal dans le produit |
|---|---|---|
| **Agent de scolarité** | Numérise/importe les PV, vérifie et corrige les données extraites, transmet pour validation | Traiter les dossiers vite et sans erreur |
| **Enseignant** | Dépose son PV, vérifie que les notes extraites correspondent à celles attribuées, signale une fraude | Confiance dans la fidélité de la transcription |
| **Chef de département** | Un par filière (3 filières) ; valide/rejette les dossiers de sa filière | Garantir la conformité avant clôture |
| **Responsable académique** | Rôle transversal, peut cumuler les 3 postes de chef de département si un seul responsable existe | Superviser l'ensemble, consulter les rapports consolidés |
| **Étudiant** | Consulte ses résultats, reçoit des notifications, peut réclamer | Accès rapide et fiable à ses notes |
| **Administrateur système** | Gère comptes, permissions, workflow, sauvegardes, sécurité | Maintenir le système opérationnel et sûr |
| **Directeur** | Consulte audit et tableaux de bord globaux | Garantir conformité et intégrité globale |
| **Moteur intelligent (acteur système)** | OpenCV + OCR entraîné + scoring + règles + notifications automatiques | Assister sans jamais décider seul |

> **Contrainte d'architecture :** les deux configurations organisationnelles (3 chefs de département distincts *ou* 1 responsable académique cumulant les 3 rôles) doivent être supportées **uniquement par attribution de rôles**, sans changement de schéma ni de code. Le champ `chef_departement_id` de `Filiere` doit être nullable.

---

## 5. Rôles et permissions (RBAC — matrice de référence)

Principe : **moindre privilège**, vérification des droits **côté serveur** à chaque requête, jamais côté client seul.

| Fonctionnalité | Agent scolarité | Enseignant | Chef département | Resp. académique | Étudiant | Admin | Directeur |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| Importer/numériser un PV | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Corriger données OCR | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Vérifier son propre PV numérisé | ❌ | ✅ (lecture + signalement fraude) | ❌ | ❌ | ❌ | ❌ | ❌ |
| Valider dossier de sa filière | ❌ | ❌ | ✅ | ✅ (les 3 filières) | ❌ | ❌ | ❌ |
| Consulter ses notes | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| Initier réclamation | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| Tableaux de bord filière | ❌ | ❌ | ✅ (sa filière) | ✅ (toutes) | ❌ | ❌ | ✅ |
| Gérer rôles / config sécurité | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ |
| Consulter piste d'audit globale | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ |
| Constituer/annoter corpus OCR | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ |
| Entraîner/évaluer le modèle OCR | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ (dev) | ❌ |

---

## 6. Cas d'usage (User Stories)

| Code | User story | Acteur |
|---|---|---|
| UC-01 | En tant qu'utilisateur, je veux m'authentifier (MFA) et gérer mon profil | Tous |
| UC-02 | En tant qu'agent de scolarité, je veux importer un lot de PV scannés | Agent |
| UC-03 | En tant que système, je veux appliquer le modèle OCR entraîné pour extraire les données | Moteur IA |
| UC-04 | En tant qu'agent, je veux vérifier/corriger les champs à faible confiance | Agent |
| UC-05 | En tant que chef de département, je veux valider le report de notes de ma filière | Chef département |
| UC-06 | En tant qu'étudiant, je veux consulter mes notes en ligne | Étudiant |
| UC-07 | En tant qu'étudiant, je veux initier une réclamation | Étudiant |
| UC-08 | En tant que responsable académique, je veux superviser l'état des délibérations de toutes les filières | Resp. académique |
| UC-09 | En tant qu'administrateur, je veux gérer les rôles et la sécurité | Admin |
| UC-10 | En tant que directeur, je veux consulter la piste d'audit et les tableaux de bord globaux | Directeur |
| UC-11 | En tant que responsable/directeur, je veux générer des rapports statistiques exportables | Resp./Directeur |
| UC-12 | En tant qu'enseignant, je veux vérifier mes notes après numérisation de mon PV | Enseignant |
| UC-13 | En tant qu'agent/admin, je veux constituer, annoter et gérer le corpus d'entraînement OCR | Agent/Admin |
| UC-14 | En tant que développeur, je veux entraîner et évaluer le moteur OCR | Dev/Moteur IA |

Chaque UC doit être décliné par l'agent en tickets avec critères d'acceptation (Given/When/Then) avant implémentation.

---

## 7. Spécifications fonctionnelles détaillées (modules)

### 7.1 Module Gestion des utilisateurs et des accès
- Inscription / connexion / déconnexion sécurisées
- Authentification multifacteur (MFA) obligatoire pour les rôles à privilèges élevés
- RBAC (moindre privilège)
- Gestion du profil et des préférences de notification
- Verrouillage de compte après tentatives infructueuses + journalisation des connexions

### 7.2 Module Gestion des promotions et des étudiants
- Création/mise à jour du référentiel étudiants
- Structuration par filière, niveau, année académique
- Import/gestion des listes d'étudiants
- Recherche multicritère + historique académique

### 7.3 Module Numérisation et Extraction (OCR)
- Interface d'import de lots de PV scannés (réservée agent de scolarité)
- Numérisation via scanner/imprimante externe (pas d'app mobile en V1)
- Prétraitement d'image : redressement (deskew), débruitage, binarisation (OpenCV)
- Détection/segmentation des zones d'intérêt (en-tête, tableau de notes, matricules, signatures)
- Application du modèle OCR entraîné pour lire notes, matricules, codes matières
- Pré-remplissage automatique + **score de confiance par champ**

### 7.4 Module Entraînement et évaluation du moteur OCR (cœur technique)
Voir section 8 (spécification dédiée, détaillée).

### 7.5 Module Fiabilisation — Human-in-the-loop
- Interface de vérification comparative (document original vs données extraites)
- Surlignage automatique des zones à faible confiance
- Outils de correction manuelle
- Accès de l'enseignant à son propre PV numérisé pour vérification
- Journalisation des corrections → réutilisables comme données d'enrichissement du corpus (boucle de rétroaction)
- Validation finale de cohérence avant intégration

### 7.6 Module Workflow et délibération
- Circuit paramétrable : agent → chef de département / responsable académique → validation finale
- Calcul automatique des moyennes + détection d'erreurs de calcul
- Règles automatiques de notation :
  - Fraude constatée → note **00/20** automatique sur le module concerné
  - Cumul ≥ 8h d'absence non justifiée (seuil configurable) → note **00/20** automatique sur le module
- États du dossier : brouillon, en vérification, validé, clôturé (voir section 9 pour la machine à états complète)
- Rejet / demande de réexamen avec motif obligatoire

### 7.7 Module Notifications et rétroaction
- Notifications multicanal (in-app, email) pour publication de notes
- Alertes de suivi pour agents (dossiers en attente, échéances)
- Gestion des réclamations étudiants

### 7.8 Module Tableau de bord et reporting
- Indicateurs : taux de traitement des PV, erreurs détectées, délais moyens, CER/WER en production
- Filtres dynamiques (semestre, UE, filière, promotion)
- Export PDF/Excel
- Visualisation graphique de l'avancement (par filière + consolidé)

### 7.9 Module Audit et journalisation
- Journalisation horodatée de toute action (qui a saisi/validé/modifié quoi)
- Logs inviolables (append-only, non modifiables même par l'admin applicatif)
- Piste d'audit permettant de reconstituer chaque décision

---

## 8. Pipeline OCR — spécification technique détaillée (cœur scientifique du projet)

C'est la partie la plus critique du projet ; elle ne doit **jamais** être simplifiée en un simple `pytesseract.image_to_string()`.

### 8.1 Étapes du pipeline (à implémenter comme un pipeline reproductible, versionné)

| Étape | Description | Sortie attendue |
|---|---|---|
| 1. Échantillonnage du corpus | Collecte de PV représentatifs (filières, semestres, enseignants) | Corpus brut anonymisé |
| 2. Prétraitement homogène | Standardisation résolution, deskew, binarisation adaptative | Images normalisées |
| 3. Annotation contrôlée | Double annotation (recoupement) des champs critiques (bounding boxes + transcription vérité-terrain), via un outil type Label Studio | Corpus annoté |
| 4. Split train/val/test | Répartition ~70/15/15, **sans chevauchement de documents** entre jeux | 3 sous-ensembles disjoints |
| 5. Augmentation de données | Rotation, bruit, contraste/luminosité, flou | Corpus enrichi |
| 6. Entraînement (fine-tuning) | Fine-tuning du moteur LSTM Tesseract à partir d'un modèle pré-entraîné, suivi de la courbe d'apprentissage, early stopping | Modèle `.traineddata` candidat |
| 7. Évaluation quantitative | CER, WER, précision par champ critique (matricule, note), matrice de confusion de caractères | Rapport d'évaluation |
| 8. Itération | Cycle entraînement → évaluation → enrichissement ciblé du corpus jusqu'au seuil cible | Nouvelle version du modèle |
| 9. Versionnement et déploiement | Export `.traineddata` versionné, intégré au microservice OCR, historique conservé | Modèle en production traçable |

### 8.2 Seuil de qualité cible
- **CER < 3 %** sur les champs numériques (notes, matricules) sur jeu de test indépendant, avant toute mise en production.
- Toute régression du CER en production au-delà du seuil toléré déclenche une procédure de réentraînement.

### 8.3 Entités de données dédiées (voir modèle complet section 10)
- `Document_Corpus` (documents du corpus, répartition train/val/test)
- `Annotation` (vérité-terrain par champ, coordonnées de zone)
- `Modele_OCR` (versions, CER, WER, statut actif/archivé)

### 8.4 Boucle de rétroaction
Les corrections effectuées en production par les agents (module 7.5) doivent pouvoir être réinjectées comme données d'enrichissement du corpus d'entraînement — prévoir un mécanisme d'export des corrections vers le format d'annotation.

### 8.5 Gouvernance IA
Le module intelligent (OCR + scoring + détection d'anomalies) est **uniquement un outil d'aide à la décision**. Aucune décision administrative engageante (validation finale, proclamation des résultats) n'est automatisée sans confirmation humaine explicite. Toute alerte IA doit être explicable et présentée à l'agent, qui garde l'autorité finale.

---

## 9. Workflow et machine à états du dossier (PV)

**Circuit nominal :**
`Réception (Upload) → Prétraitement & OCR → Vérification (Human-in-the-loop) → Validation hiérarchique → Intégration et clôture → Publication`

### 9.1 États et transitions

| État | Description | Transitions possibles |
|---|---|---|
| Soumis | PV importé par l'enseignant/agent | → En traitement (OCR) |
| En traitement | Extraction automatique par le modèle OCR | → En vérification / Erreur d'extraction |
| Erreur d'extraction | Données illisibles, incohérentes ou sous le seuil de confiance | → Soumis (re-scan) |
| En vérification | Validation humaine des données extraites | → En validation / Complément requis |
| En validation | Examen par le responsable de délibération | → Validé / Rejeté / Complément requis |
| Complément requis | Demande de correction ou de PV original | → En vérification |
| Validé | Notes confirmées et conformes | → Intégré |
| Intégré | Notes inscrites dans la base académique | → Publié |
| Publié | Résultats consultables par l'étudiant | → Archivé |
| Rejeté | Annulation motivée de la saisie | → Archivé |

> **Règle d'implémentation :** chaque transition doit être enregistrée dans le journal d'audit (utilisateur, horodatage, ancien état, nouvel état, motif le cas échéant). Modéliser cette machine à états explicitement dans le code (pas d'état implicite déduit d'un booléen).

### 9.2 Règles d'automatisation associées
- **Affectation intelligente** : répartition automatique des lots de PV entre agents selon leur charge de travail
- **SLA** : surveillance des délais de traitement, escalade hiérarchique en cas de retard
- **Moteur d'alerte** : notifications automatiques à la publication ou en cas de blocage
- **Piste d'audit automatique** sur chaque transition

---

## 10. Modèle de données (entités principales)

| Entité | Attributs principaux | Relations |
|---|---|---|
| Utilisateur | id, nom, email, mot_de_passe_hash, rôle, statut_MFA | 1,n avec Journal d'audit |
| Filière | id, nom, chef_departement_id (**nullable**) | 1,n avec Étudiant |
| Étudiant | id, matricule, nom, prénom, filière_id, niveau | 1,n avec Note |
| Procès-Verbal (PV) | id, nom_fichier, code_matière, semestre, date_scan, statut_traitement, depose_par_id | 1,n avec Note |
| Note | id, valeur, coefficient, crédit, état_validation, motif_penalite | n,1 avec Étudiant ; n,1 avec PV |
| Absence | id, étudiant_id, module_id, durée_heures, date, justifiée (bool) | n,1 avec Étudiant |
| Workflow_Etape | id, nom_étape, ordre, acteur_responsable, délai_SLA | 1,n avec PV |
| Décision | id, type (valider/rejeter), motif, date, auteur_id | n,1 avec PV |
| Alerte | id, niveau, message, date_creation, statut_lecture | n,1 avec PV |
| Document_Corpus | id, nom_fichier, type_gabarit, jeu (train/val/test), date_annotation | 1,n avec Annotation |
| Annotation | id, document_id, champ, valeur_verite_terrain, coordonnées_zone | n,1 avec Document_Corpus |
| Modele_OCR | id, version, date_entrainement, CER, WER, statut (actif/archivé) | 1,n avec PV (modèle utilisé) |
| Journal d'audit | id, action, acteur_id, cible_id, date_heure, details_json | n,1 avec Utilisateur |

**Précisions de conception à respecter :**
- `Filiere.chef_departement_id` **nullable** pour supporter les deux configurations organisationnelles (3 chefs distincts ou 1 responsable cumulant les 3 rôles).
- `Note.motif_penalite` trace explicitement pourquoi une note 00/20 a été attribuée automatiquement (fraude ou absence), pour la distinguer d'une évaluation normale.
- `Document_Corpus`/`Annotation` sont **indépendantes** des PV traités en production (ne pas mélanger données d'entraînement et données de production).
- `Modele_OCR` conserve l'historique complet des versions pour tracer quel modèle a traité quel PV.
- Le Journal d'audit doit être **append-only** (pas d'UPDATE/DELETE applicatif possible).

---

## 11. Architecture technique

### 11.1 Architecture générale
Architecture en couches, orientée services :
- **Front-end (présentation)** : interface web réactive pour les services de scolarité et les étudiants
- **Back-end (logique métier)** : API sécurisée orchestrant flux, workflows, accès
- **Microservice intelligent** : OCR entraîné + scoring, isolé pour maintenance et montée en charge indépendantes (pics lors des examens) ; héberge aussi le pipeline d'entraînement/évaluation
- **Persistance** : base relationnelle garantissant l'intégrité transactionnelle des notes

### 11.2 Stack technologique retenue

| Composant | Technologie | Justification |
|---|---|---|
| Interface web | React.js | Composants réutilisables, adapté aux tableaux de bord interactifs |
| Backend / API | PHP – Laravel | Sécurisé par défaut (CSRF, XSS, SQLi), adapté aux workflows administratifs |
| Microservice IA / OCR | Python – FastAPI | Standard IA, API asynchrones performantes ; héberge entraînement + inférence |
| Entraînement OCR | Tesseract (tesstrain / fine-tuning LSTM) + OpenCV | Moteur OCR entraînable + prétraitement/segmentation |
| Base de données | PostgreSQL | SGBD relationnel robuste, ACID, contraintes complexes |
| Authentification | JWT + MFA | Standard moderne, sécurisation forte des sessions |
| Déploiement | Docker | Portabilité, isolation dev/prod |

### 11.3 Découpage en services (suggestion d'implémentation)
```
siarn/
├── frontend/           # React.js — interface scolarité + portail étudiant
├── backend-api/        # Laravel — logique métier, workflow, RBAC, notifications
├── ocr-service/        # Python/FastAPI — prétraitement OpenCV, inférence OCR
│   ├── training/        # pipeline d'entraînement/évaluation (tesstrain)
│   └── inference/       # service d'inférence en production
├── docs/                # documentation technique, ADRs, manuel utilisateur
└── infra/               # Docker, scripts de déploiement, CI/CD
```

---

## 12. Exigences non fonctionnelles

| Catégorie | Exigence |
|---|---|
| Performance | Temps de réponse applicatif < 3 s ; traitement OCR par PV < 10 s ; pagination/indexation |
| Précision OCR | CER cible < 3 % sur champs numériques, mesuré sur jeu de test indépendant |
| Disponibilité | ≥ 99 % en exploitation ; architecture tolérante aux pannes |
| Scalabilité | Doit absorber les pics de charge en périodes d'examens |
| Utilisabilité | UI/UX intuitive, accessibilité de base |
| Compatibilité | Responsive, multi-navigateurs |
| Maintenabilité | Code modulaire, documenté, couvert par tests automatisés (non-régression) |
| Portabilité | Déploiement conteneurisé Docker |
| Traçabilité | Journalisation exhaustive, horodatée, inviolable |
| Conformité | Protection des données personnelles (chiffrement, anonymisation du corpus) |

---

## 13. Exigences de sécurité

### 13.1 Identités et accès
- MFA obligatoire pour rôles à privilèges élevés (agents, validateurs, admins)
- Mots de passe : politique de robustesse stricte, hachage Bcrypt/Argon2
- Sessions à durée de vie limitée, révocation immédiate, déconnexion après inactivité
- Verrouillage progressif après tentatives infructueuses (anti brute-force)

### 13.2 Contrôle d'accès
- RBAC strict, vérification systématique **côté serveur**

### 13.3 Chiffrement
- HTTPS (TLS 1.2/1.3) pour toutes les données en transit
- Chiffrement des données au repos (BDD, fichiers sensibles)
- Coffre-fort de secrets avec rotation périodique des clés
- Anonymisation systématique pour statistiques, rapports et corpus d'entraînement OCR

### 13.4 Sécurité applicative (OWASP)
- Requêtes paramétrées (anti injection SQL/NoSQL)
- Échappement de sortie + jetons anti-CSRF (anti XSS/CSRF)
- Assainissement rigoureux des entrées côté serveur
- Upload de fichiers : contrôle type/taille + analyse antivirale
- Rate limiting + en-têtes de sécurité renforcés

### 13.5 Traçabilité et disponibilité
- Piste d'audit horodatée et inviolable de toute action sensible
- Détection proactive d'activités suspectes + alertes admin
- Sauvegardes chiffrées testées régulièrement + plan de restauration documenté

### 13.6 Conformité et gouvernance
- Consentement, finalité explicite, minimisation des données (y compris corpus OCR)
- Revues de sécurité et tests de robustesse de base
- Conformité à la réglementation burkinabè sur la protection des données personnelles

---

## 14. Contraintes du projet

- **Connectivité instable** → architecture tolérante (cache local, requêtes légères)
- **Ressources serveurs limitées** → code optimisé
- **Corpus d'entraînement** → dépend de la disponibilité de PV réels/reconstitués et de l'accord institutionnel (même anonymisés) ; conditionne directement la précision atteignable
- **Sécurité** → haut niveau de protection requis (données sensibles)
- **Réglementaire** → conformité protection des données + procédures internes de délibération
- **Délais** → calendrier académique du mémoire de fin de cycle à respecter
- **Ergonomie** → interfaces simples pour agents à littératie numérique variable

---

## 15. Livrables attendus

1. Cahier des charges complet (référence de ce PRD)
2. Dossier de conception (UML : cas d'utilisation, classes, séquences, états)
3. Corpus d'entraînement OCR annoté (train/val/test) + rapport de méthodologie d'annotation
4. Moteur OCR entraîné + rapport d'évaluation (CER, WER, analyse d'erreurs) + algorithme de scoring d'anomalies
5. Solution applicative (interface scolarité + interface étudiant)
6. Infrastructure de données (PostgreSQL) + scripts Docker
7. Documentation technique (API, architecture, méthodologie d'entraînement) + manuel utilisateur
8. Mémoire de fin de cycle (rapport final)

---

## 16. Roadmap / Épics pour l'agent IA

À dérouler dans cet ordre (les phases 3 et 5 — corpus et OCR — sont les plus critiques et peuvent démarrer en parallèle du développement applicatif dès que l'architecture est posée) :

| Épic | Contenu | Dépend de |
|---|---|---|
| E0 — Socle technique | Repo, Docker Compose (frontend/backend/ocr-service/db), CI, conventions de code, schéma BDD initial (section 10) | — |
| E1 — Authentification & RBAC | Auth JWT + MFA, gestion des rôles, verrouillage de compte, journalisation des connexions | E0 |
| E2 — Référentiels | Gestion filières/étudiants/promotions, import de listes | E1 |
| E3 — Import & prétraitement PV | Upload de lots, OpenCV (deskew, débruitage, binarisation), segmentation des zones | E1, E2 |
| E4 — Constitution du corpus OCR | Interface d'annotation (ou intégration Label Studio), gestion Document_Corpus/Annotation, split train/val/test | E0 |
| E5 — Entraînement & évaluation OCR | Pipeline fine-tuning Tesseract, calcul CER/WER, versionnement Modele_OCR | E4 |
| E6 — Inférence OCR en production | Intégration du modèle validé dans le microservice, scoring de confiance par champ | E3, E5 |
| E7 — Human-in-the-loop | Interface de vérification comparative, surlignage faible confiance, corrections, boucle de rétroaction vers le corpus | E6 |
| E8 — Workflow & règles métier | Machine à états (section 9), calcul des moyennes, règles fraude/absence, SLA, escalade | E2, E7 |
| E9 — Notifications & réclamations | Notifications multicanal, gestion des réclamations étudiants | E8 |
| E10 — Tableaux de bord & reporting | Indicateurs, filtres, export PDF/Excel | E8 |
| E11 — Audit & sécurité transverse | Journal d'audit append-only, revue OWASP, chiffrement, rate limiting | Transverse dès E1 |
| E12 — Portail étudiant | Consultation des notes, notifications, réclamation (UI dédiée) | E8, E9 |
| E13 — Tests, recette, déploiement | Tests automatisés, recette technique, documentation finale, mise en production Docker | Toutes |

---

## 17. Analyse des risques

| Risque | Impact | Mesure de mitigation |
|---|---|---|
| Corpus d'entraînement insuffisant ou peu représentatif | Élevé | Collecte anticipée dès l'analyse, partenariat scolarité, augmentation de données, documents reconstitués en complément |
| Précision insuffisante du modèle OCR | Élevé | Human-in-the-loop systématique, itérations d'entraînement, enrichissement ciblé, seuil de confiance déclenchant vérification obligatoire |
| Fuite ou vol de données | Élevé | Chiffrement, RBAC strict, logs inviolables, anonymisation du corpus |
| Indisponibilité du service | Moyen | Architecture tolérante aux pannes, sauvegardes automatisées, plan de reprise |
| Instabilité de connexion | Moyen | Requêtes légères, cache client, tolérance aux coupures temporaires |
| Résistance au changement | Moyen | UX centrée utilisateur, formation, manuel clair |
| Dépassement des délais | Moyen | Priorisation MVP par lots, suivi hebdomadaire, arbitrage rapide sur l'entraînement du modèle |

---

## 18. Critères d'acceptation globaux (Definition of Done du produit)

- [ ] Un PV papier scanné peut être importé, prétraité, extrait par OCR, vérifié par un agent, validé hiérarchiquement, intégré et publié — de bout en bout.
- [ ] Le CER mesuré sur le jeu de test du modèle OCR déployé est < 3 % sur les champs numériques.
- [ ] Aucune note n'est publiée sans passage par la vérification humaine.
- [ ] Toute transition d'état et toute action sensible est journalisée de façon inviolable.
- [ ] Le RBAC empêche tout accès non autorisé même via manipulation côté client (vérifié par tests).
- [ ] Les deux configurations organisationnelles (3 chefs de département / 1 responsable académique) fonctionnent sans changement de code.
- [ ] Les règles automatiques de pénalité (fraude → 0/20, absence ≥ seuil → 0/20) sont appliquées et tracées avec motif.
- [ ] Un tableau de bord permet de suivre en temps réel l'avancement par filière et de façon consolidée.
- [ ] L'ensemble est déployable via Docker.

---

## 19. Glossaire

- **CER / WER** : Character Error Rate / Word Error Rate — mesures de précision OCR
- **PV** : Procès-verbal (document papier de notes remis par l'enseignant)
- **SLA** : Service Level Agreement — délai cible de traitement
- **RBAC** : Role-Based Access Control
- **Human-in-the-loop** : mécanisme de validation humaine des sorties de l'IA
- **Fine-tuning** : réentraînement d'un modèle pré-existant sur un corpus spécifique
- **Vérité-terrain (ground truth)** : transcription de référence utilisée pour évaluer l'OCR
