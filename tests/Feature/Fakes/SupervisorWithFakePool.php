<?php

namespace PocketArc\HorizonDatabase\Tests\Feature\Fakes;

use Illuminate\Support\Collection;
use Laravel\Horizon\Supervisor;

class SupervisorWithFakePool extends Supervisor
{
    /**
     * Create a single process pool using the test fake.
     *
     * @return Collection
     */
    protected function createSingleProcessPool()
    {
        return collect([new FakeProcessPool($this->options->queue)]);
    }

    /**
     * Create a process pool per queue using the test fake.
     *
     * @return Collection
     */
    protected function createProcessPoolPerQueue()
    {
        return collect(explode(',', $this->options->queue))->map(function ($queue) {
            return new FakeProcessPool($queue);
        });
    }
}
