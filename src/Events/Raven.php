<?php

namespace ChijiokeIbekwe\Raven\Events;

use ChijiokeIbekwe\Raven\Data\Scroll;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class Raven
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public Scroll $scroll)
    {
        //
    }
}
