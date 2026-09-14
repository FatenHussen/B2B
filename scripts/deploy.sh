#!/usr/bin/env bash
#
# Deploy the API on the server. Run from anywhere:
#
#   sudo /var/www/sentrax/backend/scripts/deploy.sh
#
# Nothing is touched until three pre-flight checks pass:
#
#   1. the database named in .env exists and the credentials open it;
#   2. a mysqldump of it landed on disk, is non-empty and carries the
#      "Dump completed" trailer — an interrupted dump has a size too;
#   3. (after composer install --no-dev) the application boots. A provider
#      that only exists in require-dev — TelescopeServiceProvider was the
#      case here — is exactly what this catches before migrate runs.
#
# Any failure stops the script on the spot and names the step. The app is
# then left in maintenance mode on purpose: bringing a half-deployed tree
# back up is worse than a 503.
#
# Overridable through the environment:
#   APP_USER / APP_GROUP  owner the tree is handed back to when run as root
#   BACKUP_DIR            where dumps go            (default /var/backups/sentrax-db)
#   DEPLOY_BRANCH         branch to fast-forward     (default main)
#   PHP_BIN / COMPOSER_BIN
#   QUEUE_SERVICE         systemd unit to restart after the deploy, if any

set -Eeuo pipefail

APP_USER="${APP_USER:-www-data}"
APP_GROUP="${APP_GROUP:-www-data}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/sentrax-db}"
DEPLOY_BRANCH="${DEPLOY_BRANCH:-main}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
QUEUE_SERVICE="${QUEUE_SERVICE:-}"

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

STEP="startup"
PREV_COMMIT=""
IN_MAINTENANCE=0
MYSQL_DEFAULTS=""

step() { STEP="$1"; printf '\n==> %s\n' "$1"; }

# Printed on every failure after `artisan down`: the tree is left as it is,
# and the operator decides between fixing forward and rolling back.
maintenance_hint() {
    if [ "$IN_MAINTENANCE" = 1 ]; then
        printf '!! The app is still in maintenance mode.\n' >&2
        if [ -n "$PREV_COMMIT" ]; then
            printf '!! Roll back:  git checkout %s && %s install --no-dev --optimize-autoloader && %s artisan up\n' \
                "$PREV_COMMIT" "$COMPOSER_BIN" "$PHP_BIN" >&2
        fi
    fi
}

fail() {
    printf '\n!! FAILED at step "%s": %s\n' "$STEP" "$1" >&2
    maintenance_hint
    exit "${2:-1}"
}

on_err() {
    local code=$?
    printf '\n!! FAILED at step "%s" (exit %d)\n' "$STEP" "$code" >&2
    maintenance_hint
    exit "$code"
}

on_exit() {
    if [ -n "$MYSQL_DEFAULTS" ]; then
        rm -f "$MYSQL_DEFAULTS"
    fi
    # git / composer / artisan run as the caller; give the tree back to the
    # web user so the next request (and the next www-data run) can write.
    if [ "$(id -u)" = 0 ] && [ -d "$PROJECT_DIR/storage" ]; then
        chown -R "$APP_USER:$APP_GROUP" "$PROJECT_DIR" 2>/dev/null || true
    fi
}

trap on_err ERR
trap on_exit EXIT

# Read one key from .env: last definition wins, quotes and CR stripped,
# commented-out lines ignored.
env_get() {
    local value
    value="$(grep -E "^${1}=" "$PROJECT_DIR/.env" | tail -n 1 | cut -d= -f2- || true)"
    value="${value%$'\r'}"
    value="${value%\"}"; value="${value#\"}"
    value="${value%\'}"; value="${value#\'}"
    printf '%s' "$value"
}

main() {
    cd "$PROJECT_DIR"

    step "tooling"
    for bin in git curl mysql mysqldump gzip "$PHP_BIN" "$COMPOSER_BIN"; do
        command -v "$bin" >/dev/null 2>&1 || fail "'$bin' is not on PATH"
    done
    [ -f .env ] || fail ".env is missing in $PROJECT_DIR"
    [ -f artisan ] || fail "$PROJECT_DIR is not a Laravel root"
    if [ "$(id -u)" = 0 ]; then
        export COMPOSER_ALLOW_SUPERUSER=1
    fi

    step "environment sanity"
    local app_env app_debug app_url
    app_env="$(env_get APP_ENV)"
    app_debug="$(env_get APP_DEBUG)"
    app_url="$(env_get APP_URL)"
    printf 'APP_ENV=%s  APP_DEBUG=%s  APP_URL=%s\n' "$app_env" "$app_debug" "$app_url"
    [ "$app_debug" != "true" ] || fail "APP_DEBUG=true — refusing to deploy with debug on"
    if [ "$app_env" != "production" ]; then
        printf 'WARNING: APP_ENV is "%s", not "production". OTP_BYPASS and other dev switches stay active.\n' "$app_env"
    fi

    step "database exists"
    local db_host db_port db_name db_user db_pass
    db_host="$(env_get DB_HOST)"; db_port="$(env_get DB_PORT)"
    db_name="$(env_get DB_DATABASE)"; db_user="$(env_get DB_USERNAME)"
    db_pass="$(env_get DB_PASSWORD)"
    [ -n "$db_name" ] || fail "DB_DATABASE is empty in .env"
    [ -n "$db_user" ] || fail "DB_USERNAME is empty in .env"
    [ "$db_user" != "root" ] || fail "DB_USERNAME=root — the app must use its own database user"

    # Credentials go through a 0600 defaults file, never the command line.
    MYSQL_DEFAULTS="$(mktemp)"
    chmod 600 "$MYSQL_DEFAULTS"
    printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\n' \
        "${db_host:-127.0.0.1}" "${db_port:-3306}" "$db_user" "$db_pass" > "$MYSQL_DEFAULTS"

    local found
    found="$(mysql --defaults-extra-file="$MYSQL_DEFAULTS" -N -B -e \
        "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='${db_name}'")" \
        || fail "cannot connect to MySQL at ${db_host}:${db_port} as ${db_user}"
    [ "$found" = "$db_name" ] || fail "database '${db_name}' does not exist on ${db_host}:${db_port}"
    printf 'database %s reachable as %s@%s\n' "$db_name" "$db_user" "$db_host"

    step "backup"
    mkdir -p "$BACKUP_DIR" || fail "cannot create $BACKUP_DIR"
    [ -w "$BACKUP_DIR" ] || fail "$BACKUP_DIR is not writable"
    local dump
    dump="$BACKUP_DIR/${db_name}_$(date +%Y%m%d_%H%M%S).sql.gz"
    # pipefail: a mysqldump error is not masked by gzip succeeding.
    mysqldump --defaults-extra-file="$MYSQL_DEFAULTS" \
        --single-transaction --quick --routines --triggers --no-tablespaces \
        "$db_name" | gzip > "$dump" \
        || { rm -f "$dump"; fail "mysqldump failed"; }
    [ -s "$dump" ] || { rm -f "$dump"; fail "backup file is empty: $dump"; }
    if ! gzip -dc "$dump" | tail -n 1 | grep -q 'Dump completed'; then
        rm -f "$dump"
        fail "backup is truncated (no 'Dump completed' trailer): $dump"
    fi
    printf 'backup ok: %s (%s)\n' "$dump" "$(du -h "$dump" | cut -f1)"

    step "maintenance mode on"
    "$PHP_BIN" artisan down
    IN_MAINTENANCE=1

    step "git pull ($DEPLOY_BRANCH)"
    PREV_COMMIT="$(git rev-parse --short HEAD)"
    git fetch origin "$DEPLOY_BRANCH"
    git merge --ff-only "origin/$DEPLOY_BRANCH"
    printf '%s -> %s\n' "$PREV_COMMIT" "$(git rev-parse --short HEAD)"

    step "composer install --no-dev"
    "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction --prefer-dist

    step "application boots"
    # Registers every provider. A class that only exists under require-dev
    # dies here, before migrate has touched the schema.
    "$PHP_BIN" artisan about --only=environment >/dev/null \
        || fail "the application does not boot after composer install --no-dev"
    printf 'boot ok\n'

    step "migrate"
    "$PHP_BIN" artisan migrate --force

    step "cache"
    "$PHP_BIN" artisan config:cache
    "$PHP_BIN" artisan route:cache
    "$PHP_BIN" artisan event:cache
    # no view:cache — this repository has no view layer (CLAUDE.md rule 6)

    step "queue"
    "$PHP_BIN" artisan queue:restart
    if [ -n "$QUEUE_SERVICE" ]; then
        systemctl restart "$QUEUE_SERVICE"
    fi

    step "maintenance mode off"
    "$PHP_BIN" artisan up
    IN_MAINTENANCE=0

    step "health"
    local health
    health="$(curl -fsS --max-time 15 "${app_url%/}/api/v1/health")" \
        || fail "health endpoint did not answer 200: ${app_url%/}/api/v1/health"
    printf '%s\n' "$health"
    if ! printf '%s' "$health" | grep -q '"status":"ok"'; then
        printf 'WARNING: deployed, but health is not "ok" — read the checks above.\n'
    fi

    printf '\n==> done: %s at %s\n' "$DEPLOY_BRANCH" "$(git rev-parse --short HEAD)"
}

main "$@"
exit
