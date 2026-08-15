<?php

namespace PocketArc\HorizonDatabase\Repositories;

use Illuminate\Contracts\Queue\Factory;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Support\Str;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\Contracts\WorkloadRepository;
use Laravel\Horizon\WaitTimeCalculator;

class DatabaseWorkloadRepository implements WorkloadRepository
{
    /**
     * The queue factory implementation.
     *
     * @var Factory
     */
    public $queue;

    /**
     * The wait time calculator instance.
     *
     * @var WaitTimeCalculator
     */
    public $waitTime;

    /**
     * The supervisor repository implementation.
     *
     * @var SupervisorRepository
     */
    private $supervisors;

    /**
     * Create a new repository instance.
     *
     * @return void
     */
    public function __construct(
        QueueFactory $queue,
        WaitTimeCalculator $waitTime,
        SupervisorRepository $supervisors,
    ) {
        $this->queue = $queue;
        $this->waitTime = $waitTime;
        $this->supervisors = $supervisors;
    }

    /**
     * Get the current workload of each queue.
     *
     * @return array<int, array{"name": string, "length": int, "wait": int, "processes": int, "split_queues": null|array<int, array{"name": string, "wait": int, "length": int}>}>
     */
    public function get()
    {
        $processes = $this->processes();

        return collect($this->waitTime->calculate())
            ->map(function ($waitTime, $queue) use ($processes) {
                [$connection, $queueName] = explode(':', $queue, 2);

                $totalProcesses = $processes[$queue] ?? 0;

                $length = ! Str::contains($queue, ',')
                    ? collect([$queueName => $this->readyNow($connection, $queueName)])
                    : collect(explode(',', $queueName))->mapWithKeys(function ($queueName) use ($connection) {
                        return [$queueName => $this->readyNow($connection, $queueName)];
                    });

                $splitQueues = Str::contains($queue, ',') ? $length->map(function ($length, $queueName) use ($connection, $totalProcesses, &$wait) {
                    return [
                        'name' => $queueName,
                        'length' => $length,
                        'wait' => $wait += $this->waitTime->calculateTimeToClear($connection, $queueName, $totalProcesses),
                    ];
                }) : null;

                return [
                    'name' => $queueName,
                    'length' => $length->sum(),
                    'wait' => $waitTime,
                    'processes' => $totalProcesses,
                    'split_queues' => $splitQueues,
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Get the number of jobs ready to be processed for the given connection / queue.
     *
     * @param  string  $connection
     * @param  string  $queueName
     * @return int
     */
    protected function readyNow($connection, $queueName)
    {
        $queue = $this->queue->connection($connection);

        if (method_exists($queue, 'readyNow')) {
            return $queue->readyNow($queueName);
        }

        return $queue->size($queueName);
    }

    /**
     * Get the number of processes of each queue.
     *
     * @return array
     */
    private function processes()
    {
        return collect($this->supervisors->all())
            ->pluck('processes')
            ->reduce(function ($final, $queues) {
                foreach ($queues as $queue => $processes) {
                    $final[$queue] = isset($final[$queue]) ? $final[$queue] + $processes : $processes;
                }

                return $final;
            }, []);
    }
}
