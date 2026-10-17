#!/usr/bin/env sh
# DropDeck — entrypoint
# Env:
#   RUN_MIGRATIONS=true   -> jalankan migrate (HANYA di service "app")
#   WAIT_FOR_DB=true      -> tunggu MariaDB siap
#   APP_ENV=production    -> cache config/route/view/event
set -e

cd /app
export PHP_OPCACHE_VALIDATE_TIMESTAMPS="${PHP_OPCACHE_VALIDATE_TIMESTAMPS:-1}"

if [ "${WAIT_FOR_DB:-true}" = "true" ] && [ -n "${DB_HOST:-}" ]; then
  echo "[entrypoint] menunggu database ${DB_HOST}:${DB_PORT:-3306} ..."
  i=0
  until php -r '
    $h = getenv("DB_HOST"); $p = (int)(getenv("DB_PORT") ?: 3306);
    $s = @fsockopen($h, $p, $e, $m, 2); if ($s) { fclose($s); exit(0); } exit(1);
  '; do
    i=$((i+1))
    if [ "$i" -ge 60 ]; then echo "[entrypoint] database tidak siap"; exit 1; fi
    sleep 2
  done
fi

if [ -f artisan ] && [ -d vendor ]; then
  if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "[entrypoint] migrate --force"
    php artisan migrate --force
  fi

  if [ "${APP_ENV:-production}" = "production" ]; then
    echo "[entrypoint] optimize"
    php artisan optimize
  fi
fi

exec "$@"
