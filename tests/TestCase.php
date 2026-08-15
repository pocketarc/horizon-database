<?php

namespace PocketArc\HorizonDatabase\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Schema;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\TagRepository;
use Laravel\Horizon\HorizonServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use PocketArc\HorizonDatabase\HorizonDatabaseServiceProvider;

abstract class TestCase extends BaseTestCase
{
    /**
     * Setup the test case.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // CI databases persist between runs, so start from a known-empty schema.
        Schema::connection('testing')->dropAllTables();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->createQueueTables();
    }

    /**
     * Create the framework's own queue tables.
     *
     * These are built inline rather than published from the framework's
     * migration stubs, so the suite runs against a fixed schema across Laravel
     * versions.
     *
     * @return void
     */
    protected function createQueueTables()
    {
        $schema = Schema::connection('testing');

        $schema->create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        $schema->create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        $schema->create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });
    }

    /**
     * Get the service providers for the package.
     *
     * @param  Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            HorizonServiceProvider::class,
            HorizonDatabaseServiceProvider::class,
        ];
    }

    /**
     * Configure the environment.
     *
     * @param  Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('horizon-database.enabled', true);
        $app['config']->set('horizon-database.connection', 'testing');

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', $this->databaseConfig());

        $app['config']->set('queue.default', 'database');
        $app['config']->set('queue.connections.database', [
            'driver' => 'database',
            'connection' => 'testing',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
            'after_commit' => false,
        ]);

        $app['config']->set('queue.failed', [
            'driver' => 'database-uuids',
            'database' => 'testing',
            'table' => 'failed_jobs',
        ]);

        $app['config']->set('queue.batching', [
            'database' => 'testing',
            'table' => 'job_batches',
        ]);
    }

    /**
     * Get the database connection configuration for the test suite.
     *
     * @return array
     */
    protected function databaseConfig()
    {
        return match (getenv('DB_CONNECTION') ?: 'sqlite') {
            'mysql', 'mariadb' => [
                'driver' => 'mysql',
                'host' => getenv('DB_HOST') ?: '127.0.0.1',
                'port' => getenv('DB_PORT') ?: '3306',
                'database' => getenv('DB_DATABASE') ?: 'horizon',
                'username' => getenv('DB_USERNAME') ?: 'root',
                'password' => getenv('DB_PASSWORD') ?: '',
                'charset' => 'utf8mb4',
                'collation' => getenv('DB_COLLATION') ?: 'utf8mb4_unicode_ci',
                'prefix' => '',
            ],
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => getenv('DB_HOST') ?: '127.0.0.1',
                'port' => getenv('DB_PORT') ?: '5432',
                'database' => getenv('DB_DATABASE') ?: 'horizon',
                'username' => getenv('DB_USERNAME') ?: 'horizon',
                'password' => getenv('DB_PASSWORD') ?: 'password',
                'charset' => 'utf8',
                'prefix' => '',
                'schema' => 'public',
            ],
            default => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        };
    }

    /**
     * Get the total number of recent jobs.
     *
     * @return int
     */
    protected function recentJobs()
    {
        return app(JobRepository::class)->totalRecent();
    }

    /**
     * Get the total number of failed jobs.
     *
     * @return int
     */
    protected function failedJobs()
    {
        return app(JobRepository::class)->totalFailed();
    }

    /**
     * Get the total number of monitored jobs for a given tag.
     *
     * @param  string  $tag
     * @return int
     */
    protected function monitoredJobs($tag)
    {
        return app(TagRepository::class)->count($tag);
    }

    /**
     * Run the next job on the queue.
     *
     * @param  int  $times
     * @return void
     */
    protected function work($times = 1)
    {
        for ($i = 0; $i < $times; $i++) {
            $this->worker()->runNextJob('database', 'default', $this->workerOptions());
        }
    }

    /**
     * Get the queue worker instance.
     *
     * @return Worker
     */
    protected function worker()
    {
        return app('queue.worker');
    }

    /**
     * Get the options for the worker.
     *
     * @return WorkerOptions
     */
    protected function workerOptions()
    {
        return tap(new WorkerOptions, function ($options) {
            $options->sleep = 0;
            $options->maxTries = 1;
        });
    }
}
