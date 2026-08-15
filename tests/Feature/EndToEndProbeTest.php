<?php

namespace PocketArc\HorizonDatabase\Tests\Feature;

use Illuminate\Support\Facades\Queue;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\WorkloadRepository;
use PocketArc\HorizonDatabase\Tests\Feature\Jobs\BasicJob;
use PocketArc\HorizonDatabase\Tests\TestCase;

class EndToEndProbeTest extends TestCase
{
    public function test_a_job_flows_through_every_dashboard_surface()
    {
        Queue::push(new BasicJob);

        $jobs = $this->app->make(JobRepository::class);
        $this->assertSame(1, $jobs->countPending(), 'pending count after push');
        $this->assertSame(1, $jobs->totalRecent(), 'recent total after push');

        $this->work();

        $this->assertSame(1, $jobs->countCompleted(), 'completed count after work');
        $this->assertSame(0, $jobs->countFailed(), 'failed count after work');

        $recent = $jobs->getRecent();
        $this->assertCount(1, $recent);
        $this->assertSame('completed', $recent->first()->status);

        $metrics = $this->app->make(MetricsRepository::class);
        $this->assertContains('default', $metrics->measuredQueues(), 'queue was measured');
        $this->assertSame(1, $metrics->throughputForQueue('default'), 'queue throughput');
        $this->assertGreaterThanOrEqual(0, $metrics->runtimeForQueue('default'), 'queue runtime');

        $metrics->snapshot();
        $this->assertNotEmpty($metrics->snapshotsForQueue('default'), 'snapshot stored');

        $workload = $this->app->make(WorkloadRepository::class)->get();
        $this->assertIsArray($workload);
    }
}
