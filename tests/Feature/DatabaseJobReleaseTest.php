<?php

namespace PocketArc\HorizonDatabase\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PocketArc\HorizonDatabase\Tests\Feature\Jobs\BasicJob;
use PocketArc\HorizonDatabase\Tests\TestCase;

class DatabaseJobReleaseTest extends TestCase
{
    public function test_releasing_a_job_records_it_as_pending_with_its_delay()
    {
        Queue::push(new BasicJob);

        $job = Queue::connection('database')->pop('default');

        $this->assertSame('reserved', DB::table('horizon_jobs')->value('status'));

        $job->release(30);

        $record = DB::table('horizon_jobs')->first();

        $this->assertSame('pending', $record->status);
        $this->assertSame(30, (int) $record->delay);
    }

    public function test_releasing_without_a_delay_records_zero()
    {
        Queue::push(new BasicJob);

        Queue::connection('database')->pop('default')->release();

        $this->assertSame(0, (int) DB::table('horizon_jobs')->value('delay'));
    }
}
