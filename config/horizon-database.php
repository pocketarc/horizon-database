<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enable the Database Driver
    |--------------------------------------------------------------------------
    |
    | When enabled, Horizon stores its own state in your database instead of
    | Redis, and the "database" queue connection is instrumented so Horizon
    | records the jobs on it. Disable this to return Horizon to its Redis
    | driver.
    |
    */

    'enabled' => env('HORIZON_DATABASE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    |
    | The connection that holds Horizon's own tables. Leave it null to use the
    | application default. Horizon writes continuously: supervisors write a
    | heartbeat every second even with an empty queue. A dedicated connection
    | keeps that traffic off the connection serving your application.
    |
    */

    'connection' => env('HORIZON_DATABASE_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Pruning
    |--------------------------------------------------------------------------
    |
    | Redis expired Horizon's records on its own. A database does not, so rows
    | are removed explicitly. Trimming runs on the master supervisor loop,
    | which only happens while Horizon is running. Schedule "horizon:prune" so
    | records are still removed when it is not.
    |
    */

    'prune' => [
        'chunk' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Stale Job Recovery
    |--------------------------------------------------------------------------
    |
    | A worker killed with SIGKILL leaves its Horizon record stuck in the
    | "reserved" state, because a database has no key expiry to remove it. The
    | "horizon:recover-stale" command fails a record once it is this many
    | seconds past its connection's retry_after window.
    |
    */

    'recover_stale' => [
        'grace' => 60,
    ],

];
