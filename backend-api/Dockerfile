FROM php:8.4-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev \
    unzip \
    git \
    && docker-php-ext-install pdo_pgsql pgsql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --prefer-dist --no-interaction --no-scripts

COPY . .

EXPOSE 8000

# --no-reload : sans cette option, `artisan serve` ne transmet au processus
# serveur qu'une liste restreinte de variables d'environnement (pour pouvoir
# recharger proprement au changement de .env) et ecrase silencieusement les
# surcharges Docker (DB_HOST=postgres, etc.) par les valeurs brutes du .env
# monte (bug decouvert en verifiant reellement une requete HTTP en conteneur,
# pas seulement `artisan migrate`/`tinker` qui eux lisent l'env correctement).
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000", "--no-reload"]
