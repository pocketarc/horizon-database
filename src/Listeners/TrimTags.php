<?php

namespace PocketArc\HorizonDatabase\Listeners;

use Carbon\CarbonImmutable;
use Laravel\Horizon\Events\MasterSupervisorLooped;
use PocketArc\HorizonDatabase\Repositories\DatabaseTagRepository;

class TrimTags
{
    /**
     * The last time the tags were trimmed.
     *
     * @var CarbonImmutable|null
     */
    public $lastTrimmed;

    /**
     * How many minutes to wait between trims.
     *
     * @var int
     */
    public $frequency = 5;

    /**
     * Create a new listener instance.
     *
     * @return void
     */
    public function __construct(public DatabaseTagRepository $tags) {}

    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle(MasterSupervisorLooped $event)
    {
        if (! isset($this->lastTrimmed)) {
            $this->lastTrimmed = CarbonImmutable::now()->subMinutes($this->frequency + 1);
        }

        if ($this->lastTrimmed->lte(CarbonImmutable::now()->subMinutes($this->frequency))) {
            $this->tags->trimExpired();

            $this->lastTrimmed = CarbonImmutable::now();
        }
    }
}
