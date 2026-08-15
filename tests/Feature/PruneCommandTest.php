<?php

namespace PocketArc\HorizonDatabase\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\JobPayload;
use PocketArc\HorizonDatabase\Tests\TestCase;

class PruneCommandTest extends TestCase
{
    public function test_it_removes_expired_locks()
    {
        DB::table('horizon_locks')->insert([
            ['key' => 'expired', 'expires_at' => now()->getTimestamp() - 60],
            ['key' => 'live', 'expires_at' => now()->getTimestamp() + 600],
        ]);

        $this->artisan('horizon:prune')->assertSuccessful();

        $this->assertSame(['live'], DB::table('horizon_locks')->pluck('key')->all());
    }

    public function test_it_removes_commands_left_by_supervisors_that_never_read_them()
    {
        DB::table('horizon_command_queue')->insert([
            ['name' => 'old', 'command' => 'Foo', 'options' => '[]', 'created_at' => now()->subDay()->getTimestamp()],
            ['name' => 'new', 'command' => 'Foo', 'options' => '[]', 'created_at' => now()->getTimestamp()],
        ]);

        $this->artisan('horizon:prune')->assertSuccessful();

        $this->assertSame(['new'], DB::table('horizon_command_queue')->pluck('name')->all());
    }

    public function test_it_removes_recent_jobs_past_the_retention_window()
    {
        $this->app['config']->set('horizon.trim.recent', 60);

        $repository = $this->app->make(JobRepository::class);
        $repository->pushed('database', 'default', new JobPayload(json_encode([
            'id' => 'old', 'displayName' => 'foo',
        ])));

        DB::table('horizon_jobs')->where('id', 'old')->update([
            'created_at' => now()->subDay()->getTimestamp(),
        ]);

        $this->artisan('horizon:prune')->assertSuccessful();

        $this->assertSame(0, DB::table('horizon_jobs')->count());
    }

    public function test_it_keeps_jobs_inside_the_retention_window()
    {
        $repository = $this->app->make(JobRepository::class);
        $repository->pushed('database', 'default', new JobPayload(json_encode([
            'id' => 'recent', 'displayName' => 'foo',
        ])));

        $this->artisan('horizon:prune')->assertSuccessful();

        $this->assertSame(1, DB::table('horizon_jobs')->count());
    }

    public function test_it_trims_in_batches_of_the_configured_size()
    {
        // A smaller chunk must produce strictly more delete statements for the
        // same rows. One unbatched delete would empty the table just as well,
        // so the final row count on its own does not show whether the prune
        // batched.
        $this->assertGreaterThan(
            $this->countDeletesWhilePruning(chunk: 1000),
            $this->countDeletesWhilePruning(chunk: 2),
        );
    }

    /**
     * Prune five expired jobs and return how many delete statements the
     * prune command ran.
     */
    protected function countDeletesWhilePruning(int $chunk): int
    {
        DB::table('horizon_jobs')->delete();
        $this->app['config']->set('horizon-database.prune.chunk', $chunk);

        $repository = $this->app->make(JobRepository::class);

        foreach (range(1, 5) as $i) {
            $repository->pushed('database', 'default', new JobPayload(json_encode([
                'id' => "old-{$chunk}-{$i}", 'displayName' => 'foo',
            ])));
        }

        DB::table('horizon_jobs')->update(['created_at' => now()->subDay()->getTimestamp()]);

        $deletes = 0;
        DB::listen(function ($query) use (&$deletes) {
            if (str_starts_with(strtolower(trim($query->sql)), 'delete')
                && str_contains($query->sql, 'horizon_jobs')) {
                $deletes++;
            }
        });

        $this->artisan('horizon:prune')->assertSuccessful();

        $this->assertSame(0, DB::table('horizon_jobs')->count());

        return $deletes;
    }
}
