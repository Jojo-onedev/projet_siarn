# Référence API — backend-api

Base URL locale : `http://localhost:8000/api`. Toutes les routes sauf `/auth/connexion` et `/auth/mfa/verifier` exigent un en-tête `Authorization: Bearer <jwt>`. Le RBAC (§5 du PRD) est vérifié **côté serveur** à chaque requête ; la colonne « RBAC » ci-dessous donne le rôle exigé par le middleware de route — quand une vérification supplémentaire a lieu dans le contrôleur (ex. portée « sa filière »), c'est signalé explicitement.

## Authentification (§7.1, §13.1)

| Méthode | Route | RBAC | Notes |
|---|---|---|---|
| POST | `/auth/connexion` | public | Limité par `throttle:auth` (défaut 10/min/IP, configurable). Retourne soit un `token` direct (rôle sans MFA obligatoire, ou MFA pas encore configuré), soit `{"statut":"mfa_requis","mfa_token":...}` si le MFA est déjà actif. |
| POST | `/auth/mfa/verifier` | public (nécessite un `mfa_token` valide) | Deuxième facteur TOTP, `throttle:auth`. |
| POST | `/auth/deconnexion` | authentifié | Révoque la session JWT courante (`sessions_jwt.revoque`). |
| GET | `/auth/moi` | authentifié | Profil de l'utilisateur courant. |
| POST | `/auth/mfa/activer` | authentifié | Génère un secret TOTP (accessible même si le MFA n'est pas encore configuré — c'est le but). |
| POST | `/auth/mfa/confirmer` | authentifié | Confirme l'enrôlement MFA avec un code TOTP valide, passe `statut_mfa` à `true`. |

Toutes les routes ci-dessous exigent en plus `mfa.requise` (bloque les rôles à MFA obligatoire — `agent_scolarite`, `chef_departement`, `responsable_academique`, `admin`, `directeur` — tant que le MFA n'est pas configuré).

## Administration (§7.1)

| Méthode | Route | RBAC |
|---|---|---|
| GET | `/utilisateurs` | `admin` |
| POST | `/utilisateurs` | `admin` |

## Référentiels (§7.2)

| Méthode | Route | RBAC |
|---|---|---|
| GET | `/filieres`, `/filieres/{id}` | `agent_scolarite, chef_departement, responsable_academique, directeur, admin` |
| POST | `/filieres`, PUT `/filieres/{id}` | `agent_scolarite, admin` |
| GET | `/modules` | lecture large (idem filières) |
| POST | `/modules`, PUT `/modules/{id}` | `agent_scolarite, admin` |
| GET | `/etudiants`, `/etudiants/{id}` | lecture large |
| GET | `/etudiants/{id}/moyenne` | lecture large — calcul automatique de moyenne pondérée (§7.6) |
| POST | `/etudiants`, PUT `/etudiants/{id}` | `agent_scolarite, admin` | `POST /etudiants` accepte optionnellement `email` + `mot_de_passe` (les deux ensemble ou aucun) : si fournis, crée **et lie** dans la même transaction un compte de connexion (`Utilisateur` rôle `etudiant`, `statut_mfa=false`) via `etudiants.utilisateur_id`. Ajouté après un test manuel : sans ce lien, aucun étudiant réel ne pouvait se connecter au portail (§7.6) — ni `POST /etudiants` ni `POST /utilisateurs` ne le faisaient auparavant. |
| POST | `/etudiants/import` | `agent_scolarite, admin` — import CSV (attention BOM UTF-8 Excel, déjà géré). Ne crée **jamais** de compte de connexion (pas d'email dans le CSV) — utiliser `POST /etudiants` au cas par cas pour un accès portail. |

## Procès-verbaux / workflow (§7.3–§7.7, §9.1)

| Méthode | Route | RBAC | Notes |
|---|---|---|---|
| GET | `/pv`, `/pv/{id}` | `agent_scolarite, chef_departement, responsable_academique, directeur, admin` | |
| GET | `/pv/{id}/image?type=original\|pretraitee` | idem | Ajouté lors du frontend F4 (écart initial : aucune route ne servait l'image, alors que §7.5 exige l'affichage de l'image à côté de la valeur extraite). Streaming authentifié direct depuis le disque `pv` (jamais d'URL publique, `serve=false`) ; `type` par défaut `original`. |
| GET | `/pv/{id}/notes` | idem | |
| POST | `/pv/import` | **`agent_scolarite` uniquement** (§5 : aucun autre rôle, pas même admin) | Multipart, `fichiers[]`. Déclenche synchrone : prétraitement OpenCV → segmentation → extraction OCR (modèle actif) → transition `soumis → en_traitement → en_verification` (ou `erreur_extraction` si confiance moyenne sous le seuil plancher ou modèle absent). |
| POST | `/pv/{id}/verifier` | `agent_scolarite` uniquement | Corrections champ par champ ; transition automatique vers `en_validation` seulement quand **tous** les champs ont une `valeur_validee`. Toute correction divergente de l'OCR brut alimente la boucle de rétroaction corpus (§8.4). |
| POST | `/pv/{id}/notes` | `agent_scolarite` uniquement | Saisie structurée des notes par étudiant (une zone `tableau_notes` = un bloc de texte, pas de reconnaissance de tableau automatique — la structuration reste humaine). |
| POST | `/pv/{id}/valider` | `chef_departement` (sa filière — vérifié en contrôleur via `Filiere.chef_departement_id`) ou `responsable_academique` (les 3 filières) | `decision`: `valider`/`rejeter`/`complement_requis`. Si `valider` : transition `valide` puis auto-transition `integre` (notes marquées `valide`). |
| POST | `/pv/{id}/publier` | `agent_scolarite, responsable_academique, admin` | Uniquement depuis `integre`. Déclenche notification de publication (§7.7). Action délibérée, jamais en cascade automatique. |
| POST | `/notes/{id}/signaler-fraude` | `enseignant`, et seulement s'il est l'enseignant référent du module (`modules.enseignant_id`) | Applique la pénalité 00/20 avec motif tracé. |

## Absences (§7.6)

| Méthode | Route | RBAC |
|---|---|---|
| GET/POST | `/absences` | `agent_scolarite, enseignant, admin` |

Déclenche automatiquement la pénalité 00/20 si le cumul d'absence non justifiée dépasse `SEUIL_ABSENCE_HEURES` (défaut 8h, configurable).

## Réclamations (§7.7, UC-07)

| Méthode | Route | RBAC |
|---|---|---|
| POST | `/reclamations` | `etudiant` uniquement |
| GET | `/reclamations` | `agent_scolarite, chef_departement, responsable_academique, admin` |
| POST | `/reclamations/{id}/repondre` | idem |

## Corpus OCR (§8.1)

| Méthode | Route | RBAC |
|---|---|---|
| GET | `/corpus/documents`, `/corpus/documents/{id}` | `agent_scolarite, admin` |
| POST | `/corpus/documents` | `agent_scolarite, admin` |
| POST | `/corpus/documents/{id}/annotations` | `agent_scolarite, admin` |
| POST | `/corpus/repartir` | `agent_scolarite, admin` — répartition train/val/test |

## Modèle OCR (§8.3)

| Méthode | Route | RBAC |
|---|---|---|
| GET | `/modeles-ocr` | `admin` |

L'entraînement lui-même (E5) n'est **pas** exposé en API — c'est un job Docker Compose distinct (`docker compose run --rm ocr-training`, profil `training`), jamais accessible en HTTP, cf. `docs/DEPLOIEMENT.md`.

## Tableaux de bord (§7.8)

| Méthode | Route | RBAC |
|---|---|---|
| GET | `/dashboard/pv` | `chef_departement, responsable_academique, directeur` (sa filière pour le chef, vérifié en contrôleur) |
| GET | `/dashboard/ocr` | idem |
| GET | `/dashboard/pv/export` | idem |

Note : `agent_scolarite` et `admin` sont **explicitement exclus** de cette ligne de la matrice §5 — ce ne sont pas des rôles de pilotage.

## Audit (§7.9, §13.5, UC-10)

| Méthode | Route | RBAC |
|---|---|---|
| GET | `/audit` | `admin, directeur` uniquement |

## Portail étudiant (§7.2, §7.6, §7.7, UC-06)

| Méthode | Route | RBAC |
|---|---|---|
| GET | `/mon-profil` | `etudiant` |
| GET | `/mes-notes` | `etudiant` — uniquement les notes des PV au statut `publie` |
| GET | `/mes-alertes` | `etudiant` |
| GET | `/mes-reclamations` | `etudiant` |

Résolution systématique via `etudiants.utilisateur_id` du compte connecté — jamais un identifiant fourni par le client (vérifié par `test_etudiant_ne_peut_pas_consulter_le_profil_dun_autre`).

## ocr-service (microservice interne, non exposé au frontend)

`backend-api` est le seul consommateur prévu de ce service (`OCR_SERVICE_URL`, interne au réseau Docker).

| Méthode | Route | Rôle |
|---|---|---|
| POST | `/pretraitement` | Déskew/débruitage/binarisation + détection des zones (retourne l'image prétraitée + coordonnées pixel des zones, pour surlignage frontend §7.5) |
| POST | `/extraction` | Applique le modèle OCR (`modele_ocr_version` transmis par backend-api) sur les zones segmentées, retourne texte + score de confiance par champ |

Ces deux endpoints sont volontairement séparés (jamais un seul endpoint qui « fait tout ») pour permettre au frontend d'afficher le prétraitement avant même qu'un modèle OCR actif existe.
