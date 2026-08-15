<?php

namespace PocketArc\HorizonDatabase\Console;

use Illuminate\Console\Command;
use Illuminate\Database\ConnectionResolverInterface;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\JobPayload;
use PocketArc\HorizonDatabase\Exceptions\JobLostException;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Fails Horizon records left stranded in the "reserved" state.
 *
 * Redis expired these records on its own, because the job hash carried a TTL. A
 * database has no equivalent, so a worker killed with SIGKILL leaves a record
 * that stays reserved forever. A record still reserved well past its
 * connection's retry_after window has no worker left to complete it.
 */
#[AsCommand(name: 'horizon:recover-stale')]
class RecoverStaleJobsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'horizon:recover-stale
                            {--chunk=1000 : The number of records to process per query}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fail Horizon records stuck in the reserved state';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(JobRepository $jobs, ConnectionResolverInterface $resolver)
    {
        $connection = $resolver->connection(config('horizon-database.connection'));
        $grace = (int) config('horizon-database.recover_stale.grace', 60);
        $recovered = 0;

        foreach ($this->retryAfterByConnection() as $name => $retryAfter) {
            $cutoff = now()->getTimestamp() - $retryAfter - $grace;

            do {
                $stale = $connection->table('horizon_jobs')
                    ->where('status', 'reserved')
                    ->where('connection', $name)
                    ->where('reserved_at', '<', $cutoff)
                    ->limit((int) $this->option('chunk'))
                    ->get();

                foreach ($stale as $job) {
                    // Route through the repository so a recovered job ends in
                    // exactly the same state as one that failed normally.
                    $jobs->failed(
                        JobLostException::forJob($job->id, $name),
                        $name,
                        $job->queue,
                        new JobPayload($job->payload)
                    );

                    $recovered++;
                }
            } while ($stale->isNotEmpty());
        }

        $this->components->info(
            $recovered === 1
                ? 'Recovered 1 stale job.'
                : "Recovered {$recovered} stale jobs."
        );

        return self::SUCCESS;
    }

    /**
     * Get the retry_after window of each configured queue connection.
     *
     * @return array<string, int>
     */
    protected function retryAfterByConnection()
    {
        $connections = [];

        foreach ((array) config('queue.connections', []) as $name => $options) {
            if (is_array($options)) {
                $connections[$name] = (int) ($options['retry_after'] ?? 90);
            }
        }

        return $connections;
    }
}
