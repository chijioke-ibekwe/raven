<?php

namespace ChijiokeIbekwe\Raven\Jobs;

use ChijiokeIbekwe\Raven\Data\NotificationContext;
use ChijiokeIbekwe\Raven\Data\Scroll;
use ChijiokeIbekwe\Raven\Enums\ChannelType;
use ChijiokeIbekwe\Raven\Exceptions\RavenContextNotFoundException;
use ChijiokeIbekwe\Raven\Exceptions\RavenInvalidDataException;
use DateInterval;
use DateTimeInterface;
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
        $channels = $scroll->getChannels() ?? $context->channels;
        $recipients = $scroll->getRecipients();

        $jobClass = $context->encrypted
            ? EncryptedRavenChannelJob::class
            : RavenChannelJob::class;

        foreach ($channels as $channel) {
            $channel_type = ChannelType::tryFrom(strtoupper($channel));

            if (is_null($channel_type)) {
                throw new RavenInvalidDataException("Notification context has an invalid channel: $channel");
            }

            foreach ($recipients as $recipient) {
                Log::info("Dispatching channel job for context $context->name through channel $channel_type->name");

                if ($scroll->isSync()) {
                    $jobClass::dispatchSync($scroll, $context, $channel_type, $recipient);
                } else {
                    $pendingDispatch = $jobClass::dispatch($scroll, $context, $channel_type, $recipient);

                    if (! is_null($scroll->getAfterCommit())) {
                        $scroll->getAfterCommit()
                            ? $pendingDispatch->afterCommit()
                            : $pendingDispatch->beforeCommit();
                    }

                    $delay = $this->resolveDelay($scroll, $channel_type);

                    if ($delay) {
                        $pendingDispatch->delay($delay);
                    }
                }
            }
        }
    }

    private function resolveDelay(Scroll $scroll, ChannelType $channelType): DateTimeInterface|DateInterval|int|null
    {
        $delay = $scroll->getDelay();

        if (is_array($delay)) {
            return $delay[strtolower($channelType->name)] ?? null;
        }

        return $delay;
    }
}
