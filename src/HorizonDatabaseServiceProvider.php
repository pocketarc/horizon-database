<?php

namespace PocketArc\HorizonDatabase;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Horizon\Contracts\HorizonCommandQueue;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\ProcessRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\Contracts\TagRepository;
use Laravel\Horizon\Contracts\WorkloadRepository;
use Laravel\Horizon\Events\MasterSupervisorLooped;
use Laravel\Horizon\Lock;
use PocketArc\HorizonDatabase\Connectors\DatabaseConnector;
use PocketArc\HorizonDatabase\Listeners\MarshalDatabaseFailedEvent;
use PocketArc\HorizonDatabase\Listeners\TrimTags;

class HorizonDatabaseServiceProvider extends ServiceProvider
{
    /**
     * Horizon's own bindings, replaced with their database-backed equivalents.
     *
     * Horizon registers these as singletons during register(), so rebinding
     * them here in boot() takes precedence without forking Horizon.
     *
     * @var array<class-string, class-string>
     */
    protected array $databaseBindings = [
        Lock::class => DatabaseLock::class,
        HorizonCommandQueue::class => DatabaseHorizonCommandQueue::class,
        JobRepository::class => Repositories\DatabaseJobRepository::class,
        MasterSupervisorRepository::class => Repositories\DatabaseMasterSupervisorRepository::class,
        MetricsRepository::class => Repositories\DatabaseMetricsRepository::class,
        ProcessRepository::class => Repositories\DatabaseProcessRepository::class,
        SupervisorRepository::class => Repositories\DatabaseSupervisorRepository::class,
        TagRepository::class => Repositories\DatabaseTagRepository::class,
        WorkloadRepository::class => Repositories\DatabaseWorkloadRepository::class,
    ];

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/horizon-database.php', 'horizon-database');
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->offerPublishing();

        if (! config('horizon-database.enabled')) {
            return;
        }

        foreach ($this->databaseBindings as $abstract => $concrete) {
            $this->app->singleton($abstract, $concrete);
        }

        $this->registerQueueConnector();

        Event::listen(JobFailed::class, MarshalDatabaseFailedEvent::class);
        Event::listen(MasterSupervisorLooped::class, TrimTags::class);

        $this->registerCommands();
    }

    /**
     * Instrument the "database" queue connector so Horizon records its jobs.
     *
     * @return void
     */
    protected function registerQueueConnector()
    {
        $this->app->afterResolving(QueueManager::class, function (QueueManager $manager) {
            $manager->addConnector('database', function () {
                return new DatabaseConnector($this->app->make(ConnectionResolverInterface::class));
            });
        });
    }

    /**
     * Register the package's commands and their schedule.
     *
     * @return void
     */
    protected function registerCommands()
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            Console\PruneCommand::class,
            Console\RecoverStaleJobsCommand::class,
        ]);

        // Without this, trimming only runs on the master supervisor loop, so
        // rows accumulate whenever Horizon is not running.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('horizon:prune')->hourly();
            $schedule->command('horizon:recover-stale')->everyFiveMinutes();
        });
    }

    /**
     * Set up the resource publishing groups.
     *
     * @return void
     */
    protected function offerPublishing()
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/horizon-database.php' => config_path('horizon-database.php'),
        ], 'horizon-database-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'horizon-database-migrations');
    }
}
