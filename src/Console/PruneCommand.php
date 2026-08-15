<?php

namespace PocketArc\HorizonDatabase\Console;

use Illuminate\Console\Command;
use Illuminate\Database\ConnectionResolverInterface;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use PocketArc\HorizonDatabase\Repositories\DatabaseTagRepository;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'horizon:prune')]
class PruneCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'horizon:prune';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove expired Horizon records from the database';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(
        JobRepository $jobs,
        DatabaseTagRepository $tags,
        SupervisorRepository $supervisors,
        MasterSupervisorRepository $masters,
        ConnectionResolverInterface $resolver,
    ) {
        // Reuse the repositories' own trimming so the retention windows stay
        // defined in one place. Horizon runs these on the master supervisor
        // loop, and this command covers the times it is not running.
        $this->task('Recent jobs', fn () => $jobs->trimRecentJobs());
        $this->task('Failed jobs', fn () => $jobs->trimFailedJobs());
        $this->task('Monitored jobs', fn () => $jobs->trimMonitoredJobs());
        $this->task('Tags', fn () => $tags->trimExpired());
        $this->task('Supervisors', fn () => $supervisors->flushExpired());
        $this->task('Master supervisors', fn () => $masters->flushExpired());

        $connection = $resolver->connection(config('horizon-database.connection'));

        $this->task('Locks', fn () => $connection->table('horizon_locks')
            ->where('expires_at', '<=', now()->getTimestamp())
            ->delete());

        // Commands addressed to a supervisor that has already died are never
        // read, so these rows would otherwise accumulate forever.
        $this->task('Command queue', fn () => $connection->table('horizon_command_queue')
            ->where('created_at', '<', now()->subHour()->getTimestamp())
            ->delete());

        return self::SUCCESS;
    }

    /**
     * Run a pruning step and report how many rows it removed.
     *
     * @param  string  $label
     * @return void
     */
    protected function task($label, callable $callback)
    {
        $removed = $callback();

        $this->components->twoColumnDetail(
            $label, is_int($removed) ? "{$removed} removed" : 'pruned'
        );
    }
}
