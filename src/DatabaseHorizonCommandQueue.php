<?php

namespace PocketArc\HorizonDatabase;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Query\Builder;
use Laravel\Horizon\Contracts\HorizonCommandQueue;

class DatabaseHorizonCommandQueue implements HorizonCommandQueue
{
    /**
     * The database connection resolver instance.
     *
     * @var ConnectionResolverInterface
     */
    public $resolver;

    /**
     * Create a new command queue instance.
     *
     * @return void
     */
    public function __construct(ConnectionResolverInterface $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Push a command onto a given queue.
     *
     * @param  string  $name
     * @param  string  $command
     * @return void
     */
    public function push($name, $command, array $options = [])
    {
        $this->table()->insert([
            'name' => $name,
            'command' => $command,
            'options' => json_encode($options),
            'created_at' => str_replace(',', '.', (string) microtime(true)),
        ]);
    }

    /**
     * Get the pending commands for a given queue name.
     *
     * @param  string  $name
     * @return array
     */
    public function pending($name)
    {
        return $this->connection()->transaction(function () use ($name) {
            $records = $this->table()
                ->where('name', $name)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($records->isEmpty()) {
                return [];
            }

            $this->table()->whereIn('id', $records->pluck('id')->all())->delete();

            return $records->map(function ($record) {
                return (object) [
                    'command' => $record->command,
                    'options' => json_decode($record->options, true),
                ];
            })->all();
        });
    }

    /**
     * Flush the command queue for a given queue name.
     *
     * @param  string  $name
     * @return void
     */
    public function flush($name)
    {
        $this->table()->where('name', $name)->delete();
    }

    /**
     * Get a query builder for the horizon command queue table.
     *
     * @return Builder
     */
    protected function table()
    {
        return $this->connection()->table('horizon_command_queue');
    }

    /**
     * Get the database connection instance.
     *
     * @return ConnectionInterface
     */
    protected function connection()
    {
        return $this->resolver->connection(config('horizon-database.connection'));
    }
}
