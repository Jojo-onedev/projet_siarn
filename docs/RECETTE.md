# Recette technique — SIARN (E13)

Statut des critères d'acceptation globaux du §18 du PRD, à la date du 2026-07-13. Chaque ligne indique le statut, la preuve concrète (test automatisé et/ou vérification HTTP réelle), et les limites honnêtes le cas échéant — aucun critère n'est déclaré satisfait sans preuve vérifiable.

## Critères du §18

| # | Critère | Statut | Preuve |
|---|---|---|---|
| 1 | Un PV papier scanné peut être importé, prétraité, extrait par OCR, vérifié par un agent, validé hiérarchiquement, intégré et publié — de bout en bout. | ✅ Vérifié | Smoke-test réel E13 (§2 ci-dessous) : PV `019f5de1-80c3-72cd-b09b-e794cbdb9fff` a parcouru `soumis → en_traitement → en_verification → en_validation → valide → integre → publie` via de vrais appels HTTP (upload multipart réel, vraie extraction Tesseract avec un modèle entraîné réel, correction humaine réelle, validation par un vrai compte chef de département, publication réelle). |
| 2 | Le CER mesuré sur le jeu de test du modèle OCR déployé est < 3 % sur les champs numériques. | ⚠️ Non vérifiable en l'état — voir limite documentée | Le pipeline d'entraînement est réel (fine-tuning LSTM Tesseract authentique, pas un wrapper), et a mesuré CER = 0,00 % / WER = 0,00 % sur son jeu de test. **Mais** ce jeu de test est synthétique (généré par `text2image` à partir du même générateur que le train), donc structurellement trivial à 0 % — cette mesure ne reflète en rien la performance sur un vrai PV manuscrit/imprimé d'établissement. Aucun corpus institutionnel réel n'existe à ce jour pour mesurer un CER représentatif. **Ce critère ne pourra être vérifié honnêtement qu'après constitution d'un corpus réel (E4 avec de vrais PV scannés) et un ré-entraînement dessus.** |
| 3 | Aucune note n'est publiée sans passage par la vérification humaine. | ✅ Vérifié | Machine à états (`MachineEtatsPv::TRANSITIONS`) : `publie` n'est atteignable que depuis `integre`, lui-même seulement depuis `valide`, lui-même seulement depuis `en_validation`, qui n'est atteint automatiquement qu'une fois **tous** les champs `valeur_validee` renseignés par un agent (`PvController::verifier`). Aucune transition ne saute cette étape. Testé par `WorkflowTest` + confirmé en conditions réelles par le smoke-test. |
| 4 | Toute transition d'état et toute action sensible est journalisée de façon inviolable. | ✅ Vérifié | Trigger Postgres `interdire_modification_audit` (rejette tout UPDATE/DELETE sur `journal_audit`). Vérifié en conditions réelles : le smoke-test a produit 12 entrées d'audit cohérentes (`pv.import`, `pv.transition` ×7, `pv.pretraitement_reussi`, `pv.champ_verifie` ×3, `note.saisie`) consultables via `GET /audit`. |
| 5 | Le RBAC empêche tout accès non autorisé même via manipulation côté client (vérifié par tests). | ✅ Vérifié | Vérification **serveur** systématique (middleware `role:...` + vérifications complémentaires en contrôleur pour les portées, ex. chef de département limité à sa filière). 54 tests Feature backend-api couvrant les cas RBAC positifs et négatifs, plus confirmation en conditions réelles pendant le smoke-test (étudiant → 403 sur `/dashboard/pv`, agent → 409 sur re-publication). |
| 6 | Les deux configurations organisationnelles (3 chefs de département / 1 responsable académique) fonctionnent sans changement de code. | ✅ Vérifié | `Filiere.chef_departement_id` nullable (§10 du PRD, non-négociable) : une filière peut avoir un chef dédié (vérifié par le smoke-test — validation faite par un compte `chef_departement` propriétaire de la filière) ou être validée uniquement par `responsable_academique` (couvert par `WorkflowTest`, aucune contrainte NOT NULL ni logique conditionnelle sur le schéma). |
| 7 | Les règles automatiques de pénalité (fraude → 0/20, absence ≥ seuil → 0/20) sont appliquées et tracées avec motif. | ✅ Vérifié | `ReglesPenaliteService` : pénalité automatique sur cumul d'absence non justifiée ≥ seuil configurable, et sur signalement de fraude par l'enseignant référent. `motif_penalite`/`motif_penalite_detail` tracés sur `Note`. Couvert par tests Feature dédiés (E8). |
| 8 | Un tableau de bord permet de suivre en temps réel l'avancement par filière et de façon consolidée. | ✅ Vérifié (API) / ⚠️ Pas d'UI | `GET /dashboard/pv`, `/dashboard/ocr`, `/dashboard/pv/export` implémentés et testés (`DashboardTest`), avec portée filière pour chef de département et vue consolidée pour responsable académique/directeur. **Aucune interface graphique** n'existe encore (frontend = scaffold Vite uniquement) — ce critère n'est satisfait qu'au niveau API. |
| 9 | L'ensemble est déployable via Docker. | ✅ Vérifié | `docker compose up` démarre `postgres`, `backend-api`, `ocr-service`, `frontend` ; `ocr-training` reste hors de ce périmètre par défaut (profil `training`, lancé explicitement). Voir `docs/DEPLOIEMENT.md`. |

## 1. Régression automatisée (2026-07-13)

- **backend-api** (`DB_PORT=5433 php artisan test`) : **54/54 tests passés**, 129 assertions.
- **ocr-service** (`pytest -v` en conteneur) : **9/9 tests passés** (prétraitement, extraction, évaluation CER/WER).

## 2. Smoke-test end-to-end réel (2026-07-13)

Contrairement aux tests Feature (qui utilisent `DatabaseTransactions` et restent internes au framework de test), ce smoke-test exécute de vrais appels HTTP contre la stack Docker Compose en cours d'exécution (`backend-api` sur :8000, `ocr-service` sur :8001), avec un vrai modèle OCR entraîné et promu par le pipeline réel.

**Préparation :**
1. Ré-exécution réelle du pipeline d'entraînement (`docker compose run --rm ocr-training`) : fine-tuning LSTM réel sur corpus synthétique, produisant `siarn-ocr-demo-20260713233655.traineddata`, enregistré en base avec statut `candidat`.
2. Promotion en `actif` via le code réel de versionnement (`promouvoir_modele_actif`, pas une écriture SQL manuelle) — accepté car CER mesuré = 0 % < seuil 3 % (voir limite documentée au critère #2 ci-dessus : ce 0 % n'est pas représentatif).
3. Génération d'une image de PV synthétique (texte rendu via OpenCV, sans accents ni calligraphie réaliste) et création d'un jeu de données référentielles dédié (filière, module, étudiant, comptes agent/chef/étudiant) via le code applicatif réel (Eloquent).

**Déroulé (tous les appels ci-dessous sont de vrais appels HTTP, JWT réels, MFA TOTP réel calculé avec le vrai secret chiffré en base) :**

| Étape | Résultat |
|---|---|
| Connexion agent (MFA pas encore configuré) | Token émis directement |
| Activation + confirmation MFA agent (TOTP réel) | `statut_mfa = true` |
| Activation + confirmation MFA chef de département | `statut_mfa = true` |
| Connexion étudiant (rôle sans MFA obligatoire) | Token émis directement |
| **Import PV** (multipart réel, `POST /pv/import`) | Prétraitement OpenCV réel + segmentation (3 zones) + extraction Tesseract réelle avec le modèle entraîné → statut `en_verification` (confiance moyenne 19,4 %, au-dessus du seuil plancher 5 %, donc pas de rejet automatique — texte OCR brut illisible comme attendu d'un modèle jouet sur police non vue à l'entraînement, ce qui **démontre concrètement pourquoi la vérification humaine est non-négociable**) |
| Saisie note structurée (`POST /pv/{id}/notes`) | Note créée, `etat_validation = corrige` |
| Vérification humaine des 3 champs (`POST /pv/{id}/verifier`) | Toutes les valeurs corrigées → transition automatique vers `en_validation` |
| Validation hiérarchique par le chef de département propriétaire de la filière (`POST /pv/{id}/valider`, `decision: valider`) | Transition `valide` puis auto-transition `integre` |
| Publication (`POST /pv/{id}/publier`) | Transition `publie`, notification générée |
| Portail étudiant (`GET /mes-notes`) | Note (15/20, GL301, S5) visible — uniquement parce que le PV est `publie` |
| Portail étudiant (`GET /mes-alertes`) | Alerte de publication auto-générée reçue |
| RBAC négatif : étudiant → `/dashboard/pv` | 403 (attendu) |
| Garde-fou machine à états : re-publication d'un PV déjà `publie` | 409 « n'est pas prêt pour la publication » (attendu) |
| Journal d'audit (`GET /audit`, requêté en base pour ce PV) | 12 entrées cohérentes, dans l'ordre chronologique attendu |

**Conclusion du smoke-test** : le circuit nominal complet du §9.1 fonctionne de bout en bout avec de vrais services (pas de mock), un vrai modèle OCR entraîné (pas un stub), et un vrai second facteur TOTP. La seule limite est la représentativité du corpus (synthétique), documentée au critère #2.

## 3. Limites connues et écarts documentés

- **CER/WER non représentatifs** (voir critère #2) : nécessite un corpus réel d'établissement pour une mesure fiable.
- **Pas d'UI frontend métier** : le frontend est un scaffold Vite non développé à ce stade (hors périmètre de E13, qui couvre tests/recette/documentation, pas l'interface).
- **Gabarit documentaire unique** (`defaut`, zones génériques `en_tete`/`tableau_notes`/`signatures`) : à calibrer avec de vrais gabarits d'établissement (§8.1 étape 1, dépend de la collecte E4 réelle).
- **SLA simplifié** : seuil plat unique (`config('siarn.sla.delai_defaut_heures')`) plutôt qu'un délai par étape de workflow individuellement configuré (E8, écart documenté dès l'implémentation).
- **Export des tableaux de bord** : seul un export **CSV** (`GET /dashboard/pv/export`) est implémenté à ce stade ; l'export PDF/Excel mentionné au §7.8 du PRD est différé (écart documenté dès E10).
