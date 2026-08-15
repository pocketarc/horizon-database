<?php

namespace PocketArc\HorizonDatabase\Tests\Feature;

use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Repositories\RedisJobRepository;
use PocketArc\HorizonDatabase\Repositories\DatabaseJobRepository;
use PocketArc\HorizonDatabase\Tests\TestCase;

class DisabledDriverTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('horizon-database.enabled', false);
    }

    public function test_it_hands_horizon_back_to_its_own_driver()
    {
        $repository = $this->app->make(JobRepository::class);

        $this->assertNotInstanceOf(DatabaseJobRepository::class, $repository);
        $this->assertInstanceOf(RedisJobRepository::class, $repository);
    }
}
