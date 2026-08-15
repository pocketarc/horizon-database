<?php

namespace PocketArc\HorizonDatabase\Tests\Feature;

use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Laravel\Horizon\Contracts\HorizonCommandQueue;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\ProcessRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\Contracts\TagRepository;
use Laravel\Horizon\Contracts\WorkloadRepository;
use Laravel\Horizon\Lock;
use PHPUnit\Framework\Attributes\DataProvider;
use PocketArc\HorizonDatabase\DatabaseHorizonCommandQueue;
use PocketArc\HorizonDatabase\DatabaseLock;
use PocketArc\HorizonDatabase\DatabaseQueue;
use PocketArc\HorizonDatabase\Repositories;
use PocketArc\HorizonDatabase\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public static function bindingProvider(): array
    {
        return [
            [Lock::class, DatabaseLock::class],
            [HorizonCommandQueue::class, DatabaseHorizonCommandQueue::class],
            [JobRepository::class, Repositories\DatabaseJobRepository::class],
            [MasterSupervisorRepository::class, Repositories\DatabaseMasterSupervisorRepository::class],
            [MetricsRepository::class, Repositories\DatabaseMetricsRepository::class],
            [ProcessRepository::class, Repositories\DatabaseProcessRepository::class],
            [SupervisorRepository::class, Repositories\DatabaseSupervisorRepository::class],
            [TagRepository::class, Repositories\DatabaseTagRepository::class],
            [WorkloadRepository::class, Repositories\DatabaseWorkloadRepository::class],
        ];
    }

    #[DataProvider('bindingProvider')]
    public function test_it_replaces_horizons_redis_bindings($abstract, $concrete)
    {
        $this->assertInstanceOf($concrete, $this->app->make($abstract));
    }

    public function test_it_instruments_the_database_queue_connector()
    {
        $this->assertInstanceOf(
            DatabaseQueue::class,
            $this->app->make(QueueFactory::class)->connection('database')
        );
    }
}
