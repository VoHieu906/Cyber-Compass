# syntax=docker/dockerfile:1
# CyberCompass API: Laravel on serversideup/php (nginx + php-fpm, unprivileged www-data).
# Targets:
#   development: source is bind-mounted by docker-compose; used for local work and tests.
#   production:  self-contained image for ECS Fargate (api and worker services).

ARG PHP_VERSION=8.5

FROM serversideup/php:${PHP_VERSION}-fpm-nginx AS base
USER root
# intl: number/date formatting; gd: avatar re-encoding (Phase 2).
RUN install-php-extensions intl gd
USER www-data
WORKDIR /var/www/html
# Request body limits, set on purpose rather than inherited (F6): the largest bodies are 8 MB
# content-package parts on a local transfers disk (production sends parts straight to S3) and
# 5 MB lesson images. PHP and nginx must agree, or a too-large body is dropped silently.
# ContentTransferLimitsTest keeps cybercompass.content_packages.part_bytes below these.
ENV PHP_POST_MAX_SIZE=16M \
    PHP_UPLOAD_MAX_FILE_SIZE=8M \
    NGINX_CLIENT_MAX_BODY_SIZE=16M

############################################
FROM base AS development
# OPcache with timestamp validation: edits apply on the next request, but unchanged files are
# not re-read through the (slow) Windows/macOS bind mount on every request.
ENV PHP_OPCACHE_ENABLE=1 \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=1 \
    PHP_OPCACHE_REVALIDATE_FREQ=0 \
    SHOW_WELCOME_MESSAGE=false
USER root
# vendor/ lives in a named volume (docker-compose); pre-create it so the volume is owned by www-data.
RUN mkdir -p /var/www/html/vendor && chown www-data:www-data /var/www/html/vendor
COPY --chmod=755 docker/entrypoint.d/ /etc/entrypoint.d/
USER www-data

############################################
FROM base AS vendor
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist

############################################
FROM base AS production
ENV PHP_OPCACHE_ENABLE=1 \
    SHOW_WELCOME_MESSAGE=false \
    LOG_CHANNEL=stderr \
    # Cache config/routes/views at boot; migrations run as a separate one-off ECS task, never on every start.
    AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_MIGRATION=false \
    AUTORUN_LARAVEL_STORAGE_LINK=false \
    HEALTHCHECK_PATH=/up
COPY --chown=www-data:www-data --from=vendor /var/www/html/vendor ./vendor
COPY --chown=www-data:www-data . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
 && php artisan package:discover --ansi
