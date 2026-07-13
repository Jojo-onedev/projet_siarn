# Déploiement — SIARN

## 1. Prérequis

- Docker + Docker Compose.
- Un port libre 5433 (Postgres, décalé pour éviter tout conflit avec un Postgres natif sur 5432), 8000 (backend-api), 8001 (ocr-service), 5173 (frontend).

## 2. Configuration

Copier `backend-api/.env.example` vers `backend-api/.env` si absent, et renseigner au minimum :

- `APP_KEY` (générer avec `docker compose run --rm backend-api php artisan key:generate` si vide).
- `JWT_SECRET` (secret distinct de `APP_KEY` — voir `docs/ARCHITECTURE.md` §7 pour la justification).
- `AUTH_LIMITE_CONNEXION_PAR_MINUTE` (défaut 10, anti brute-force par IP sur `/auth/connexion`).

Ne jamais committer `.env`.

## 3. Démarrage de la stack applicative

```bash
docker compose up -d --build
```

Démarre `postgres`, `backend-api`, `ocr-service`, `frontend`. **`ocr-training` ne démarre jamais avec cette commande** (profil Docker Compose dédié `training`) — c'est un job ponctuel, voir §5.

Vérifier la santé :

```bash
docker compose ps
curl http://localhost:8000/up
```

## 4. Application du schéma (migrations)

Les migrations Laravel ne font qu'exécuter le SQL canonique de `infra/db/migrations/` (voir `docs/ARCHITECTURE.md` §4) :

```bash
docker compose exec backend-api php artisan migrate
```

Le répertoire SQL est monté en lecture seule dans le conteneur (`SQL_MIGRATIONS_DIR=/infra-sql-migrations`, voir `docker-compose.yml`).

## 5. Pipeline d'entraînement OCR (job ponctuel, hors `docker compose up`)

```bash
# Lancer un entraînement (démo synthétique par défaut) :
docker compose run --rm ocr-training

# Promouvoir un modèle candidat en actif (seulement si CER < seuil cible 3%,
# §8.2 — la promotion échoue explicitement sinon) :
docker compose run --rm --entrypoint python ocr-training -c "
import psycopg
from training.scripts.versionnement import promouvoir_modele_actif
conn = psycopg.connect(host='postgres', port=5432, dbname='siarn', user='siarn', password='siarn')
promouvoir_modele_actif(conn, '<modele_id>')
"
```

Le `.traineddata` produit est écrit directement dans le volume partagé `siarn_modeles_ocr`, lu par `ocr-service` sans copie ni redémarrage : promouvoir un modèle ne change que son statut en base (`modeles_ocr.statut`).

**Important** : le corpus de démonstration (`--demo`) est **synthétique** (texte généré, pas des PV réels). Un CER mesuré sur ce corpus n'est pas représentatif d'un usage en production — voir `docs/RECETTE.md` critère #2. Avant toute mise en production réelle, ré-entraîner sur un corpus constitué de vrais PV scannés et annotés (E4, `POST /corpus/documents` + `POST /corpus/documents/{id}/annotations` + `POST /corpus/repartir`).

## 6. Régression avant déploiement

```bash
# Backend (54 tests attendus) :
cd backend-api && DB_PORT=5433 php artisan test

# ocr-service (9 tests attendus) :
docker compose run --rm ocr-service sh -c "pip install --quiet pytest httpx && pytest -v"
```

## 7. Sauvegardes

Le volume `siarn_postgres_data` contient l'intégralité des données applicatives (y compris le journal d'audit append-only). Sauvegarder via `pg_dump` régulier :

```bash
docker compose exec postgres pg_dump -U siarn siarn > backup_$(date +%Y%m%d).sql
```

## 8. Rotation des secrets

`JWT_SECRET` et `APP_KEY` sont indépendants par conception (§7 de `docs/ARCHITECTURE.md`) : la rotation de l'un n'affecte pas l'autre. Faire tourner `JWT_SECRET` invalide immédiatement tous les JWT émis (les sessions actives devront se reconnecter) ; faire tourner `APP_KEY` invalide les secrets MFA chiffrés existants (`secret_mfa`) — prévoir une procédure de ré-enrôlement MFA en cas de rotation de `APP_KEY`.
