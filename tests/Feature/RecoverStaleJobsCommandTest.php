<?php

namespace PocketArc\HorizonDatabase\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\JobPayload;
use PocketArc\HorizonDatabase\Tests\TestCase;

class RecoverStaleJobsCommandTest extends TestCase
{
    public function test_it_fails_jobs_reserved_past_the_retry_window()
    {
        $this->reserveJob('stale', at: now()->getTimestamp() - 1000);

        $this->artisan('horizon:recover-stale')->assertSuccessful();

        $job = DB::table('horizon_jobs')->where('id', 'stale')->first();

        $this->assertSame('failed', $job->status);
        $this->assertNotNull($job->failed_at);
        $this->assertStringContainsString('was reserved but never', $job->exception);
    }

    public function test_it_leaves_jobs_still_inside_the_retry_window_alone()
    {
        $this->reserveJob('fresh', at: now()->getTimestamp());

        $this->artisan('horizon:recover-stale')->assertSuccessful();

        $this->assertSame('reserved', DB::table('horizon_jobs')->where('id', 'fresh')->value('status'));
    }

    public function test_it_leaves_jobs_that_are_not_reserved_alone()
    {
        $repository = $this->app->make(JobRepository::class);
        $repository->pushed('database', 'default', $this->payload('pending'));

        $this->artisan('horizon:recover-stale')->assertSuccessful();

        $this->assertSame('pending', DB::table('horizon_jobs')->where('id', 'pending')->value('status'));
    }

    /**
     * Put a job into the reserved state as of the given timestamp.
     */
    protected function reserveJob(string $id, int $at): void
    {
        $repository = $this->app->make(JobRepository::class);

        $repository->pushed('database', 'default', $payload = $this->payload($id));
        $repository->reserved('database', 'default', $payload);

        DB::table('horizon_jobs')->where('id', $id)->update(['reserved_at' => $at]);
    }

    protected function payload(string $id): JobPayload
    {
        return new JobPayload(json_encode(['id' => $id, 'displayName' => 'foo']));
    }
}
