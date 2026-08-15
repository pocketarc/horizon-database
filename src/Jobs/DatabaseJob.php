<?php

namespace PocketArc\HorizonDatabase\Jobs;

use Illuminate\Queue\Jobs\DatabaseJob as BaseDatabaseJob;
use Laravel\Horizon\Events\JobDeleted;
use Laravel\Horizon\Events\JobReleased;
use PocketArc\HorizonDatabase\DatabaseQueue;

class DatabaseJob extends BaseDatabaseJob
{
    /**
     * Release the job back into the queue after (n) seconds.
     *
     * @param  int  $delay
     * @return void
     */
    public function release($delay = 0)
    {
        parent::release($delay);

        $this->fireHorizonEvent(new JobReleased($this->getRawBody(), $delay));
    }

    /**
     * Delete the job from the queue.
     *
     * @return void
     */
    public function delete()
    {
        parent::delete();

        $this->fireHorizonEvent(new JobDeleted($this, $this->getRawBody()));
    }

    /**
     * Dispatch a Horizon event for this job.
     *
     * The parent types $database as the framework's queue, so a job built by
     * anything other than this package's connector cannot emit events. That is
     * the right outcome: Horizon has no record of the push either.
     *
     * @param  mixed  $event
     * @return void
     */
    protected function fireHorizonEvent($event)
    {
        if ($this->database instanceof DatabaseQueue) {
            $this->database->event($this->queue, $event);
        }
    }
}
