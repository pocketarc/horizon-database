# Horizon Database Driver

Run [Laravel Horizon](https://laravel.com/docs/horizon) on a relational database instead of Redis.

Horizon uses Redis twice over: it stores its own state there, and it works only
with Redis queues. This package replaces both, so Horizon runs on MySQL,
MariaDB, PostgreSQL or SQLite. The dashboard, metrics, tags, monitoring, batches
and autoscaling all behave as before.

This package does not fork Horizon. Horizon registers its repositories as
container singletons, so the package rebinds them afterwards and adds a
`database` queue connector alongside Horizon's Redis one.

## Requirements

- PHP 8.2+
- Laravel 11, 12 or 13
- Horizon 5.46+

## Installation

```bash
composer require pocketarc/horizon-database
php artisan vendor:publish --tag=horizon-database-migrations
php artisan migrate
```

Point your queue at the database connection in `config/queue.php`, then start
Horizon as usual. To publish the config file:

```bash
php artisan vendor:publish --tag=horizon-database-config
```

## Horizon still checks for a Redis client at startup

`php artisan horizon` reads `database.redis.client` before it does anything
else, and stops when the matching client is missing. That check is part of
Horizon and runs whether or not this package is installed.

The command exits with status `0`, so a process manager treats it as a clean,
immediate exit rather than a crash. Under systemd or Supervisor you get a
restart loop with nothing useful in the logs.

Install Predis, which is pure PHP and never opens a connection:

```bash
composer require predis/predis
```

```dotenv
REDIS_CLIENT=predis
```

Horizon also reads `database.redis.default` at boot without connecting to it, so
leave that block in `config/database.php`.

## Schedule the maintenance commands

Redis expired Horizon's records on its own. A database does not, so two commands
do that work. Horizon trims records on its master supervisor loop, but only
while it is running. Schedule both so the tables stay in order across deploys
and downtime.

```php
Schedule::command('horizon:prune')->hourly();
Schedule::command('horizon:recover-stale')->everyFiveMinutes();
```

The package registers both on the scheduler for you. Add them explicitly if that
registration does not take effect in your application.

`horizon:prune` removes records past their retention window, expired locks, and
commands addressed to supervisors that died before reading them.

`horizon:recover-stale` fails records stuck in the `reserved` state. A worker
killed with `SIGKILL` cannot report back, and without Redis key expiry its
record would stay `reserved` forever.

## Retention

`config/horizon.php` still controls how long records are kept. The defaults were
chosen for Redis, where storage is cheap and expiry is free. On a database the
same numbers cost more: `trim.failed` defaults to 10080 minutes, so a week of
failed jobs and their full payloads stay in the table.

Review `horizon.trim.*` against how much history you actually want.

## Give Horizon its own connection

Supervisors write a heartbeat every second and poll for commands every second.
Horizon therefore writes to the database continuously, even when no jobs are
running. On a busy application, or on a platform that bills per row read, keep
that traffic away from the connection serving requests:

```dotenv
HORIZON_DATABASE_CONNECTION=horizon
```

## When to use something else

A database is slower than Redis at this, and the cost is in write volume. Each
processed job costs roughly five or six statements, against about fifteen Redis
commands sent in two round trips.

Expect this to be comfortable into the low hundreds of jobs per second. Past
that, keep Redis.

Two things do get better. The dashboard's stats endpoint drops from fifteen to
twenty-five round trips per poll to a handful of aggregates. Job history becomes
durable and queryable with SQL instead of expiring out of memory.

SQLite is supported for local development and testing. Do not run it in
production. SQLite takes one writer at a time.

## Credit

The driver implementation comes from [Steve Bauman](https://github.com/stevebauman)'s
contribution to Horizon in [laravel/horizon#1762](https://github.com/laravel/horizon/pull/1762).
Taylor Otwell declined to maintain it upstream and suggested releasing it as a
community package. This is that package, with the production feedback from that
pull request applied, plus the pruning and stale-job recovery commands.

## Licence

MIT.
