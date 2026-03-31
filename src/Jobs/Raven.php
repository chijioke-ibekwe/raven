<?php

namespace ChijiokeIbekwe\Raven\Jobs;

use ChijiokeIbekwe\Raven\Data\NotificationContext;
use ChijiokeIbekwe\Raven\Data\Scroll;
use ChijiokeIbekwe\Raven\Enums\ChannelType;
use ChijiokeIbekwe\Raven\Exceptions\RavenContextNotFoundException;
use ChijiokeIbekwe\Raven\Exceptions\RavenInvalidDataException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Throwable;

class Raven
{
    use Dispatchable;

    public function __construct(public readonly Scroll $scroll) {}

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        $scroll = $this->scroll;
        $context_name = $scroll->getContextName();

        $contextConfig = config("notification-contexts.$context_name");

        throw_if(is_null($contextConfig), RavenContextNotFoundException::class,
            "Notification context with name $context_name does not exist");

        $context = NotificationContext::fromConfig($context_name, $contextConfig);

        if (! $context->active) {
            Log::info("Notification context $context_name is inactive. Skipping notification.");

            return;
        }

        $this->dispatchChannelJobs($scroll, $context);
    }

    /**
     * @throws RavenInvalidDataException
     */
    private function dispatchChannelJobs(Scroll $scroll, NotificationContext $context): void
    {
        $channels = $context->channels;

        $scroll->getRecipients();

        foreach ($channels as $channel) {
            $channel_type = ChannelType::tryFrom(strtoupper($channel));

            if (is_null($channel_type)) {
                throw new RavenInvalidDataException("Notification context has an invalid channel: $channel");
            }

            Log::info("Dispatching channel job for context $context->name through channel $channel_type->name");

            RavenChannelJob::dispatch($scroll, $context, $channel_type);
        }
    }
}
