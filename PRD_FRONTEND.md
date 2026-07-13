# PRD — Frontend SIARN

## 0. Objet et statut de ce document

`PRD_SIARN.md` reste la source de vérité produit (périmètre, RBAC, machine à états, modèle de données). Ce document est son **complément frontend** : il traduit ce qui a été **réellement implémenté et vérifié côté backend** (voir `docs/ARCHITECTURE.md`, `docs/API.md`, `docs/RECETTE.md`, épics E0–E13) en spécification d'interface. Là où le PRD initial décrit une intention, ce document décrit le contrat **effectif** de l'API telle qu'elle existe aujourd'hui (formats de réponse, codes d'erreur, statuts de la machine à états) — c'est ce contrat, pas une supposition, que le frontend doit consommer.

Toute divergence entre ce document et le comportement réel de l'API doit être corrigée dans ce document, jamais contournée silencieusement côté frontend (même règle de non-régression que pour le backend).

**Principe non négociable hérité du backend** : le RBAC est déjà entièrement vérifié côté serveur (§5 du PRD, confirmé par `docs/RECETTE.md` critère #5). Le frontend n'est **jamais** la frontière de sécurité — il adapte l'affichage au rôle courant pour l'ergonomie (cacher un bouton qu'un rôle ne peut pas utiliser), mais ne doit jamais supposer qu'une action est interdite au serveur simplement parce qu'elle est cachée à l'écran ; toute réponse 403/409 doit être gérée proprement (§6).

## 1. Périmètre

Application web unique (SPA), React 19 + Vite (déjà scaffoldé dans `frontend/`, aucune UI métier existante à ce jour — `App.jsx` est le template Vite par défaut). Consomme exclusivement l'API `backend-api` documentée dans `docs/API.md`. Aucun accès direct à `ocr-service` ou à Postgres depuis le frontend.

Hors périmètre (hérité de PRD_SIARN.md §3.4, non renégociable) : application mobile native, paiement en ligne, intégration tierce (ENT, plateforme nationale), toute UI de reconnaissance d'écriture cursive, tout affichage lié à une détection de fraude automatisée (le signalement de fraude reste une déclaration humaine motivée, §5).

## 2. Acteurs et écrans (mapping UC → routes API réelles)

Repris de PRD_SIARN.md §4/§6, avec le contrat API réel entre parenthèses (voir `docs/API.md` pour le détail complet) :

| UC | Écran(s) frontend | Endpoints réels consommés |
|---|---|---|
| UC-01 | Connexion, enrôlement MFA, profil | `POST /auth/connexion`, `POST /auth/mfa/verifier`, `POST /auth/mfa/activer`, `POST /auth/mfa/confirmer`, `GET /auth/moi` |
| UC-02 | Import de lot de PV | `POST /pv/import` (multipart), `GET /pv` |
| UC-03 | (pas d'écran dédié — résultat visible dans UC-04) | déclenché en synchrone par `POST /pv/import` |
| UC-04 | Vérification/correction OCR | `GET /pv/{id}`, `POST /pv/{id}/verifier`, `POST /pv/{id}/notes` |
| UC-05 | Validation hiérarchique | `POST /pv/{id}/valider` |
| UC-06 | Portail étudiant — notes | `GET /mon-profil`, `GET /mes-notes` |
| UC-07 | Portail étudiant — réclamation | `POST /reclamations`, `GET /mes-reclamations` |
| UC-08 | Supervision multi-filières | `GET /dashboard/pv`, `GET /dashboard/ocr` (sans filtre filière pour resp. académique/directeur) |
| UC-09 | Administration comptes | `GET/POST /utilisateurs` |
| UC-10 | Piste d'audit | `GET /audit` |
| UC-11 | Export de rapports | `GET /dashboard/pv/export` (CSV uniquement à ce stade — voir écart documenté §7) |
| UC-12 | Vérification enseignant + signalement fraude | `GET /pv/{id}/notes`, `POST /notes/{id}/signaler-fraude` |
| UC-13 | Corpus OCR (annotation) | `GET/POST /corpus/documents`, `POST /corpus/documents/{id}/annotations`, `POST /corpus/repartir` |
| UC-14 | Consultation des modèles OCR | `GET /modeles-ocr` (l'entraînement lui-même reste un job Docker Compose, jamais une action frontend — §5 du PRD : « Entraîner/évaluer le modèle OCR » = Admin **dev**, pas une fonctionnalité produit) |

Réclamations : réponse (`POST /reclamations/{id}/repondre`) et liste de traitement (`GET /reclamations`) accessibles à `agent_scolarite, chef_departement, responsable_academique, admin` — écran de traitement des réclamations à prévoir côté back-office, non listé explicitement dans les UC du PRD initial mais implémenté (E9).

Gestion des référentiels (filières/modules/étudiants, UC implicite du §7.2) : écrans CRUD pour `agent_scolarite`/`admin` sur `/filieres`, `/modules`, `/etudiants`, `/etudiants/import`.

## 3. Contrat d'authentification réel (à respecter précisément)

Le flux de connexion a **deux formes de réponse possibles** selon l'état du compte — le frontend doit gérer les deux, pas seulement le cas nominal :

1. `POST /auth/connexion` avec email/mot de passe →
   - Si le rôle n'exige pas de MFA (`enseignant`, `etudiant`) **ou** que le MFA n'est pas encore configuré pour ce compte : réponse `{"statut":"connecte","token":...,"utilisateur":{...}}` — connexion terminée immédiatement.
   - Si le rôle exige le MFA (`agent_scolarite`, `chef_departement`, `responsable_academique`, `admin`, `directeur`) **et** que le MFA est déjà actif : réponse `{"statut":"mfa_requis","mfa_token":...}` — l'écran doit alors demander le code TOTP et appeler `POST /auth/mfa/verifier` avec `mfa_token` + `code`.
2. Après connexion, si `utilisateur.statut_mfa === false` et que le rôle exige le MFA, **toute route métier retournera `403 {"code":"MFA_REQUIS"}`** tant que l'enrôlement (`POST /auth/mfa/activer` → afficher le QR à partir de `uri_provisionnement` → `POST /auth/mfa/confirmer`) n'est pas terminé. Le frontend doit intercepter ce code d'erreur spécifiquement et rediriger vers l'écran d'enrôlement MFA plutôt que d'afficher une erreur générique.
3. Le token JWT expire après `JWT_TTL_MINUTES` (défaut 60 min) ; il n'y a **pas d'endpoint de refresh token** à ce jour — à l'expiration, l'utilisateur doit se reconnecter. Le frontend doit détecter un `401` sur n'importe quel appel et rediriger vers l'écran de connexion (ne jamais tenter de deviner côté client si le token est encore valide).
4. `POST /auth/deconnexion` révoque la session serveur (`sessions_jwt.revoque`) — toujours l'appeler à la déconnexion explicite, ne pas se contenter de supprimer le token côté client.

**Stockage du token** : à trancher avant l'implémentation (F1) — recommandation : mémoire (store applicatif) plutôt que `localStorage`, pour limiter l'exposition XSS, avec acceptation de la perte de session au rechargement de page comme compromis pragmatique tant qu'aucun refresh token n'existe côté backend.

## 4. Écran clé : vérification humaine des champs OCR (UC-04, §7.5 non négociable)

C'est l'écran le plus sensible du produit (human-in-the-loop, §7.5). Il doit exploiter précisément la forme de réponse réelle de `GET /pv/{id}` :

- `zones_segmentees` : coordonnées pixel de chaque zone (`nom`, `x`, `y`, `largeur`, `hauteur`) — à utiliser pour **surligner** les zones sur l'image prétraitée (`chemin_image_pretraitee`, à charger séparément, cf. stockage fichiers backend), pas seulement afficher le texte extrait brut.
- `champs_extraits[]` : pour chaque champ, `valeur_ocr` (texte brut Tesseract), `score_confiance` (0–1), `verification_requise` (booléen calculé côté ocr-service), `valeur_validee` (null tant que non corrigé), `corrige_par_id`, `date_verification`.
- L'écran doit :
  - Afficher l'image (ou la zone recadrée) **à côté** de la valeur extraite (jamais uniquement le texte, pour permettre une vraie vérification visuelle).
  - Surligner visuellement les champs où `score_confiance` est faible (seuil d'affichage suggéré : reprendre `seuil_confiance_champ` du service OCR, actuellement appliqué côté backend pour `verification_requise` — ne pas dupliquer une logique de seuil différente côté frontend).
  - Permettre la correction champ par champ, avec envoi **incrémental** possible (`POST /pv/{id}/verifier` accepte des corrections partielles, plusieurs appels sont valides) — l'écran ne doit pas forcer à tout corriger en un seul envoi.
  - Afficher clairement que la transition vers `en_validation` n'a lieu **que lorsque tous les champs ont une `valeur_validee`** (retour de l'API : `statut` dans la réponse) — ne pas laisser l'utilisateur croire qu'une correction partielle valide le dossier.
  - Permettre la saisie des notes structurées par étudiant (`POST /pv/{id}/notes`) en s'appuyant visuellement sur le texte OCR de la zone `tableau_notes` comme référence (pas d'extraction automatique de tableau à ce stade — voir `docs/ARCHITECTURE.md` §6).

## 5. Visualisation de la machine à états (§9.1)

Chaque écran de détail PV doit refléter le statut réel et les transitions possibles depuis ce statut (reprendre exactement `MachineEtatsPv::TRANSITIONS`, ne pas la redéfinir côté frontend) :

```
soumis → en_traitement → en_verification ⇄ complement_requis
                              ↓
                        en_validation → valide → integre → publie → archive
                              ↓  ↓
                          rejete   (complement_requis)
                              ↓
                           archive
```

`GET /pv/{id}` retourne `historique` (liste chronologique des transitions avec acteur implicite, ancien/nouveau statut, motif) — l'afficher comme une timeline plutôt qu'un simple badge de statut, c'est une preuve de traçabilité utile à l'utilisateur (cohérent avec §7.9/l'audit).

Toute tentative d'action non valide pour l'état courant renvoie `409` avec un message explicite (ex. *"Ce PV n'est pas prêt pour la publication (statut actuel : ...)"*) — l'afficher tel quel plutôt qu'un message d'erreur générique, et désactiver côté UI les actions non pertinentes pour l'état courant (uniquement pour l'ergonomie, le 409 réel reste la garantie).

## 6. Gestion des erreurs (contrat réel, pas une supposition)

| Code | Signification réelle observée | Comportement frontend attendu |
|---|---|---|
| 401 | Token absent/invalide/expiré | Redirection vers connexion |
| 403 avec `code: MFA_REQUIS` | MFA obligatoire pour ce rôle, pas encore configuré | Redirection vers l'écran d'enrôlement MFA |
| 403 (sans code) | Rôle non autorisé pour cette route, ou portée refusée (ex. chef de département hors de sa filière) | Message d'erreur contextualisé, ne jamais masquer silencieusement |
| 409 | Transition d'état invalide / action hors séquence | Afficher le message serveur (contient le statut actuel) |
| 422 | Validation de champ (Laravel `$request->validate()`) | Afficher les erreurs par champ (`errors.<champ>`) |
| 423 | Compte verrouillé (anti brute-force, §13.1) | Message dédié, ne pas permettre de nouvelle tentative immédiate |
| 429 | Rate limit dépassé sur `/auth/connexion` (10/min/IP par défaut) | Message dédié « trop de tentatives, réessayer plus tard » |

## 7. Écarts connus à refléter dans l'UI (ne pas sur-promettre à l'utilisateur)

- **Export tableaux de bord** : CSV uniquement (`GET /dashboard/pv/export`) — ne pas afficher de bouton « Exporter en PDF/Excel » tant que cette fonctionnalité n'existe pas côté backend (écart documenté depuis E10, `docs/RECETTE.md` §3).
- **Gabarit documentaire unique** (`defaut`) : l'écran d'import ne doit pas proposer de sélecteur de gabarits multiples tant que le backend n'en gère qu'un seul générique (§8.1 étape 1, à enrichir après collecte de vrais gabarits en E4).
- **CER/WER affichés sur `GET /modeles-ocr`** : à annoter clairement dans l'UI comme mesurés sur corpus synthétique tant qu'aucun modèle n'a été entraîné sur un corpus institutionnel réel (`docs/RECETTE.md` critère #2) — ne jamais afficher un CER sans ce contexte, pour ne pas laisser croire à une performance de production.
- **Pas de refresh token** (§3 ci-dessus) : ne pas construire de mécanisme de rafraîchissement silencieux côté frontend qui masquerait une session expirée.

## 8. Choix techniques proposés (à valider avant F1, pas figés par ce document)

Le PRD_SIARN.md impose seulement « React » (§11.2). Les choix suivants sont des **propositions par défaut**, cohérentes avec un projet React 19 + Vite déjà scaffoldé, à confirmer explicitement avant de démarrer l'implémentation :

- **Routage** : `react-router` (standard de facto, aucune dépendance actuelle ne l'exclut).
- **Appels API / cache serveur** : un client fin (`fetch` + wrapper maison gérant `Authorization`, 401 global, erreurs typées) plutôt qu'une lib de data-fetching lourde à ce stade — le volume d'écrans ne justifie pas encore React Query/SWR, mais rien n'empêche de l'introduire plus tard si la complexité de cache le justifie.
- **Style** : pas de dépendance ajoutée à ce jour (`package.json` ne contient que React/Vite) — à trancher entre CSS simple/CSS Modules et une lib de composants, selon le temps disponible pour l'UI (le PRD ne fixe pas d'exigence de charte graphique).
- **Formulaires** : gestion native (`useState` + validation légère) suffisante vu la complexité modérée des formulaires actuels (import PV, vérification, référentiels) — pas de lib dédiée imposée.

## 9. Roadmap frontend proposée (miroir des épics backend E0–E13)

| Épic | Contenu | Dépend de (backend) |
|---|---|---|
| F1 — Authentification & shell applicatif | Connexion, MFA (activation + vérification), gestion de session, layout par rôle, garde de routes RBAC (affichage seulement) | E1 |
| F2 — Référentiels | CRUD filières/modules/étudiants, import CSV | E2 |
| F3 — Import & suivi des PV | Liste des PV, import de lot, statut/historique | E3, E8 |
| F4 — Vérification humaine OCR | Écran §4 ci-dessus (surlignage, correction, saisie notes) | E6, E7 |
| F5 — Validation hiérarchique | Écran de décision (valider/rejeter/complément requis), scope filière | E8 |
| F6 — Portail étudiant | Notes, alertes, réclamations | E9, E12 |
| F7 — Tableaux de bord & audit | Indicateurs par filière/consolidés, export CSV, piste d'audit | E10, E11 |
| F8 — Back-office avancé | Gestion utilisateurs (admin), corpus OCR (annotation), consultation modèles OCR | E4, E5, E1 |

À dérouler dans cet ordre suggéré, mais réévaluable — contrairement au backend, aucune dépendance technique dure n'empêche de réordonner (ex. F6 avant F5 si la priorité produit change), tant que l'API sous-jacente existe déjà (c'est le cas pour tous les épics ci-dessus, déjà livrés côté backend).
