<?php

namespace PocketArc\HorizonDatabase\Listeners;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobFailed as LaravelJobFailed;
use Laravel\Horizon\Events\JobFailed;
use PocketArc\HorizonDatabase\Jobs\DatabaseJob;

/**
 * Translates a framework job failure into Horizon's own event.
 *
 * Horizon's MarshalFailedEvent returns early unless the job is a RedisJob. This
 * listener covers database jobs, so Horizon itself does not have to change.
 * Both listeners are registered, and each ignores the other's job type.
 */
class MarshalDatabaseFailedEvent
{
    /**
     * Create a new listener instance.
     *
     * @return void
     */
    public function __construct(public Dispatcher $events) {}

    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle(LaravelJobFailed $event)
    {
        if (! $event->job instanceof DatabaseJob) {
            return;
        }

        $this->events->dispatch((new JobFailed(
            $event->exception, $event->job, $event->job->getRawBody()
        ))->connection($event->connectionName)->queue($event->job->getQueue()));
    }
}
