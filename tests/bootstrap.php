<?php

/*
|--------------------------------------------------------------------------
| Test bootstrap
|--------------------------------------------------------------------------
|
| The local Podman/Docker `app` container injects real environment variables
| (via `env_file: .env`), including APP_ENV=local and DB_CONNECTION=mariadb.
| Those "real" variables take precedence over phpunit.xml <env> entries, so
| the suite would otherwise run against the development MariaDB database and
| with CSRF enabled (419 responses).
|
| Forcing the values here guarantees the isolated SQLite in-memory database is
| always used, even when the test runner is launched from within the container.
|
*/

$overrides = [
    'APP_ENV' => 'testing',
    'APP_MAINTENANCE_DRIVER' => 'file',
    'BCRYPT_ROUNDS' => '4',
    'BROADCAST_CONNECTION' => 'null',
    'CACHE_STORE' => 'array',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
    'MAIL_MAILER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',
];

foreach ($overrides as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

require __DIR__.'/../vendor/autoload.php';
