FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    git unzip curl \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    && docker-php-ext-install \
    pdo_mysql \
    pdo_pgsql \
    zip \
    bcmath

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

# Composer runs as root inside the image; without this it disables its plugins
# and prints a warning on every build.
ENV COMPOSER_ALLOW_SUPERUSER=1
# Give slow/flaky GitHub responses more room before Composer gives up.
ENV COMPOSER_PROCESS_TIMEOUT=600

# GitHub's zipball API intermittently returns 502/504. A single failed download
# aborts the whole install, so retry a few times with a short pause, and fall
# back to cloning from source on the last attempt (--prefer-source uses
# github.com/git rather than api.github.com, which is a different code path and
# usually still works when the API is degraded).
RUN set -eux; \
    for attempt in 1 2 3; do \
        composer install --no-dev --optimize-autoloader --no-interaction --no-progress && break; \
        echo "composer install failed (attempt $attempt) - retrying in 15s"; \
        sleep 15; \
        if [ "$attempt" = "3" ]; then \
            composer install --no-dev --optimize-autoloader --no-interaction --no-progress --prefer-source; \
        fi; \
    done

RUN chmod -R 775 storage bootstrap/cache

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 10000

ENTRYPOINT ["/entrypoint.sh"]