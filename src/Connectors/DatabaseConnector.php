<?php

namespace PocketArc\HorizonDatabase\Connectors;

use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Queue\Connectors\ConnectorInterface;
use InvalidArgumentException;
use PocketArc\HorizonDatabase\DatabaseQueue;

class DatabaseConnector implements ConnectorInterface
{
    /**
     * Database connections.
     *
     * @var ConnectionResolverInterface
     */
    protected $connections;

    /**
     * Create a new connector instance.
     *
     * @return void
     */
    public function __construct(ConnectionResolverInterface $connections)
    {
        $this->connections = $connections;
    }

    /**
     * Establish a queue connection.
     *
     * @return DatabaseQueue
     */
    public function connect(array $config)
    {
        $connection = $this->connections->connection($config['connection'] ?? null);

        // The framework's DatabaseQueue takes a concrete Connection, but the
        // resolver's return type is only ConnectionInterface. Throw something
        // readable here rather than a TypeError from inside the queue.
        if (! $connection instanceof Connection) {
            throw new InvalidArgumentException(sprintf(
                'The [%s] database connection must be an %s to be used by Horizon, %s given.',
                $config['connection'] ?? 'default', Connection::class, get_debug_type($connection)
            ));
        }

        return new DatabaseQueue(
            $connection,
            $config['table'],
            $config['queue'],
            $config['retry_after'] ?? 60,
            $config['after_commit'] ?? null
        );
    }
}
