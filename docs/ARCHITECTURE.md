# Architecture technique — SIARN

Ce document décrit l'architecture telle qu'implémentée à l'issue de l'épic E13 (voir `PRD_SIARN.md` §11 pour l'architecture cible et §16 pour la roadmap). Il complète le PRD, il ne le remplace pas : en cas de divergence, ce document doit signaler explicitement l'écart (voir §5 « Écarts documentés »).

## 1. Vue d'ensemble

```
                     ┌──────────────┐
                     │   frontend   │  (Vite/React, scaffold — pas encore d'UI métier)
                     └──────┬───────┘
                            │ HTTP (JSON)
                            ▼
                     ┌──────────────┐        HTTP interne        ┌──────────────┐
                     │ backend-api  │ ───────────────────────────▶│ ocr-service  │
                     │ Laravel/PHP  │◀─────────────────────────── │ FastAPI/Py   │
                     └──────┬───────┘                             └──────┬───────┘
                            │ SQL (Eloquent)                             │ .traineddata
                            ▼                                            ▼
                     ┌──────────────┐                             volume partagé
                     │  PostgreSQL  │◀──────── psycopg (écriture ─────────┘
                     │              │           directe, versionnement)
                     └──────────────┘                             ┌──────────────┐
                            ▲                                     │ ocr-training │
                            └─────────────────────────────────────│ (profil      │
                                                                   │  "training") │
                                                                   └──────────────┘
```

Principe directeur (§11.1 du PRD) : `ocr-service` (inférence) est **stateless** et ne touche jamais Postgres directement sur le chemin d'inférence — `backend-api` reste seul propriétaire de la table `modeles_ocr` et lui transmet la version de modèle à utiliser à chaque appel. Seul `ocr-training`, un job ponctuel séparé (jamais démarré par `docker compose up`, profil Docker Compose dédié `training`), écrit directement en base via `psycopg` pour versionner un nouveau modèle (table `modeles_ocr`).

## 2. Services

| Service | Rôle | Port hôte | Notes |
|---|---|---|---|
| `postgres` | Base de données unique, schéma Postgres-spécifique (ENUM, JSONB, triggers) | 5433→5432 | Port hôte décalé pour ne pas entrer en conflit avec un Postgres natif |
| `backend-api` | API métier (Laravel 13 / PHP 8.4), RBAC, machine à états, audit | 8000 | `php artisan serve --no-reload` (voir §6, piège du rechargement d'env) |
| `ocr-service` | Microservice d'inférence OCR (FastAPI), prétraitement OpenCV, extraction Tesseract | 8001→8000 | Stateless, aucune connexion Postgres sur le chemin d'inférence |
| `ocr-training` | Pipeline d'entraînement/fine-tuning Tesseract réel | — | `profiles: ["training"]`, lancé explicitement via `docker compose run --rm ocr-training` |
| `frontend` | SPA React (Vite) | 5173 | Scaffold uniquement à ce stade — aucune UI métier développée (E13 ne couvre pas le frontend) |

Volumes nommés :
- `siarn_postgres_data` — données Postgres persistantes.
- `siarn_modeles_ocr` — partagé entre `ocr-service` et `ocr-training` : c'est là que `ocr-training` écrit le `.traineddata` final et que `ocr-service` le lit pour l'inférence (aucune copie de fichier entre conteneurs, seul le statut en base change lors d'une promotion, cf. `versionnement.py`).
- `siarn_training_travail` — répertoire de travail éphémère du pipeline d'entraînement (corpus, checkpoints).

## 3. Backend-api (Laravel)

- **Modèles Eloquent** : clés primaires UUID (trait `HasUuids`), un modèle par entité du §10 du PRD, plus deux entités ajoutées comme précision de conception hors PRD littéral et documentées comme telles : `Module` (pour rattacher notes/absences à une unité d'enseignement précise) et `Reclamation` (UC-07, §7.7).
- **Authentification** : JWT applicatif maison (`JwtService`, `JwtGuard`), avec révocation via une table dédiée `sessions_jwt` (un JWT signé ne peut pas être invalidé unilatéralement — la révocation passe par la vérification du `jti` en base à chaque requête).
- **MFA** : TOTP via `pragmarx/google2fa`, obligatoire pour les rôles à privilèges élevés (`agent_scolarite`, `chef_departement`, `responsable_academique`, `admin`, `directeur` — voir `RoleUtilisateur::mfaObligatoire()`), appliqué par le middleware `mfa.requise`, indépendamment de `auth:api`.
- **RBAC** : vérifié côté serveur systématiquement, à deux niveaux :
  1. Middleware de route `role:...` (rôle seul).
  2. Vérification supplémentaire dans le contrôleur quand le rôle seul ne suffit pas (ex. un chef de département ne peut valider que les PV de **sa propre filière** — `PvController::valider`, comparaison explicite à `Filiere.chef_departement_id`).
- **Machine à états** (`App\StateMachines\MachineEtatsPv`) : modélisation explicite du §9.1, table de transitions autorisées en dur, **jamais** de mutation directe de `ProcesVerbal.statut`. Chaque transition est journalisée deux fois : `historique_transitions_pv` (métier) et `journal_audit` (transverse, append-only).
- **Migrations** : chaque migration Laravel (`database/migrations/2026_07_13_*.php`) est un wrapper mince qui exécute le SQL canonique via `DB::unprepared(file_get_contents(...))`. Le schéma de référence vit dans `infra/db/migrations/000X_*.sql`, pas dans le PHP — voir §4.

## 4. Schéma de base de données

Le SQL canonique (source de vérité, §10 du PRD) vit dans `infra/db/migrations/` :

| Fichier | Contenu |
|---|---|
| `0001_extensions_types.sql` | Extensions Postgres, types ENUM (rôles, statuts) |
| `0002_utilisateurs_auth.sql` | `utilisateurs`, `sessions_jwt`, `journal_connexions` |
| `0003_referentiels.sql` | `filieres`, `modules`, `etudiants` |
| `0004_pv_notes_absences.sql` | `proces_verbaux`, `notes`, `absences` |
| `0005_workflow.sql` | `workflow_etapes`, `decisions`, `historique_transitions_pv` |
| `0006_corpus_ocr.sql` | `documents_corpus`, `annotations`, `modeles_ocr` |
| `0007_audit.sql` | `journal_audit` (append-only, trigger `interdire_modification_audit`) |
| `0008_pv_pretraitement.sql` | Colonnes prétraitement sur `proces_verbaux` (E3) |
| `0009_pv_extraction.sql` | Colonnes extraction OCR (`champs_extraits`, `modele_ocr_id`) (E6) |
| `0010_workflow_validation.sql` | Ajustements validation hiérarchique (E8) |
| `0011_reclamations.sql` | `reclamations`, `alertes`, `modules.enseignant_id` |

Pourquoi des migrations Laravel qui ne font qu'exécuter du SQL brut : le schéma utilise des fonctionnalités Postgres non portables (ENUM natifs, JSONB, triggers, index partiels) que le query builder Eloquent ne modélise pas nativement ; garder le SQL comme source unique évite une double maintenance schéma-PHP/schéma-SQL.

### Précisions de conception (écarts volontaires par rapport au tableau simplifié du §10 du PRD)

- `Filiere.chef_departement_id` est **nullable** (non-négociable, §10) : supporte à la fois une configuration à 1 responsable académique cumulant les 3 filières et une configuration à 3 chefs de département distincts, **sans changement de schéma**.
- `Module` (code, filière, niveau, semestre, coefficient, crédits, `enseignant_id` nullable) : ajouté car le §10 du PRD ne détaille pas d'entité « module/matière » séparée de `code_matière` sur `ProcesVerbal`, mais §7.6 (calcul de moyenne pondérée par coefficient/crédits) et §5 (un enseignant ne signale une fraude que sur *son* module) l'exigent implicitement.
- `Reclamation` : ajoutée pour UC-07/§7.7 (« Initier réclamation »), absente du tableau §10 mais explicitement listée dans les user stories.
- `documents_corpus.jeu` (train/val/test) est nullable : un document est importé au corpus avant d'être affecté à un jeu par `CorpusController::repartir` (E4).

## 5. Machine à états du PV (§9.1)

```
soumis ──────────────▶ en_traitement ──────────┬───▶ en_verification
                                                 │            │
                                                 ▼            ▼
                                   erreur_extraction    en_validation ──▶ rejete ──▶ archive
                                          │                   │  │
                                          ▼                   │  └──▶ complement_requis ──▶ en_verification
                                       soumis                 ▼
                                                            valide ──▶ integre ──▶ publie ──▶ archive
```

Implémentée in extenso dans `MachineEtatsPv::TRANSITIONS` — toute transition hors de cette table lève `TransitionInvalideException`. Vérifié en pratique par le smoke-test E13 (voir `docs/RECETTE.md`) : un PV réel a parcouru `soumis → en_traitement → en_verification → en_validation → valide → integre → publie` avec journalisation systématique à chaque étape.

## 6. Pipeline OCR (§8 du PRD)

1. **Prétraitement** (`ocr-service/inference/app/pretraitement.py`) : déskew, débruitage, binarisation OpenCV.
2. **Segmentation** (`segmentation.py`) : zones relatives par gabarit documentaire (`GABARITS["defaut"]` — à calibrer avec de vrais gabarits d'établissement collectés en E4, actuellement un point de départ générique : `en_tete` / `tableau_notes` / `signatures`).
3. **Extraction** (`ocr_engine.py`) : Tesseract via `pytesseract`, modèle custom passé explicitement en `--tessdata-dir` par tentative (jamais via la variable d'environnement globale `TESSDATA_PREFIX`, pour ne pas casser le repli dev sur l'anglais système). Score de confiance par champ, seuil déclenchant `verification_requise`.
4. **Entraînement réel** (`ocr-service/training/`) : pipeline de fine-tuning LSTM Tesseract authentique (`lstmtraining --continue_from`, pas un wrapper `pytesseract` nu) :
   - Téléchargement du modèle `fra` **best** (float, fine-tunable) depuis `tessdata_best`, jamais le `fast` (integer, apt) qui rejette `--continue_from`.
   - `combine_tessdata -u` pour déballer le checkpoint `.lstm`.
   - Génération de corpus (synthétique pour le smoke-test/démo — voir écart documenté ci-dessous), `text2image`.
   - `lstmtraining` puis `lstmtraining --stop_training` pour produire le `.traineddata` final.
   - Évaluation CER/WER sur un jeu de test.
   - Versionnement (`versionnement.py::enregistrer_modele_candidat` / `promouvoir_modele_actif`) : écriture directe Postgres via `psycopg`, statut `candidat`, promotion en `actif` seulement si CER < seuil cible (3 %, §8.2), avec archivage automatique de l'ancien actif dans la même transaction (contrainte unique partielle : un seul modèle `actif` à la fois).
5. **Rétroaction** (`RetroactionCorpusService`) : toute correction humaine qui diverge de la valeur OCR brute est exportée vers le corpus d'entraînement (§8.4), sans mélanger données de production et données d'entraînement (`documents_corpus`/`annotations` restent indépendantes des `proces_verbaux`).

**Écart documenté** : le corpus utilisé pour l'entraînement réel (E5/E13) est **synthétique** (`text2image`, texte généré), pas un corpus de PV réels de l'établissement (qui n'existe pas encore). Le pipeline est génuinement fonctionnel et exécute un vrai entraînement LSTM, mais le CER/WER mesuré sur ce corpus jouet n'est pas représentatif d'un usage en production — voir `docs/RECETTE.md` pour le détail de cette limite et pourquoi le critère d'acceptation §18 « CER < 3 % » ne peut être vérifié qu'avec un corpus institutionnel réel.

## 7. Sécurité transverse (§13 du PRD)

- **JWT + MFA** : voir §3 ci-dessus.
- **Rate limiting** : limiteur nommé Laravel (`RateLimiter::for('auth', ...)`) sur `/auth/connexion` et `/auth/mfa/verifier`, lisant `config('siarn.securite.limite_connexion_par_minute')` (défaut 10/minute/IP en production, 1000/minute en environnement de test — voir `phpunit.xml`). Complémentaire au verrouillage de compte (`utilisateurs.tentatives_echec`, par utilisateur, indépendant de l'IP).
- **Verrouillage de compte** : après N tentatives échouées (`AUTH_TENTATIVES_MAX`, défaut 5), verrouillage temporaire (`AUTH_VERROUILLAGE_MINUTES`, défaut 15).
- **En-têtes de sécurité** : middleware global `EnTetesSecurite` (`X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, etc.).
- **Journal d'audit append-only** : trigger Postgres `interdire_modification_audit` rejette tout `UPDATE`/`DELETE` applicatif sur `journal_audit`. Conséquence : les utilisateurs ayant un historique d'audit ne peuvent jamais être supprimés en dur, uniquement désactivés (`utilisateurs.actif = false`) — `journal_audit.acteur_id` est en `ON DELETE RESTRICT`, pas `SET NULL`.
- **Chiffrement** : `secret_mfa` chiffré au repos via `Crypt::encryptString` (Laravel, clé `APP_KEY`), distinct du secret JWT (`JWT_SECRET`) — la compromission de l'un ne doit pas affecter l'autre.

## 8. Ce qui n'est délibérément pas dans le périmètre V1 (§3.4 du PRD)

Aucune application mobile, aucun paiement en ligne, aucune intégration tierce (ENT, plateforme nationale), aucune reconnaissance d'écriture cursive manuscrite, aucune IA de détection de fraude avancée. Le signalement de fraude (§5, `NoteController::signalerFraude`) reste une déclaration humaine motivée par l'enseignant, jamais une détection automatique.
