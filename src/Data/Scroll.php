<?php

namespace ChijiokeIbekwe\Raven\Data;

use ChijiokeIbekwe\Raven\Exceptions\RavenInvalidDataException;
use Illuminate\Notifications\Notifiable;

class Scroll
{
    private string $contextName;

    private array $recipients = [];

    private array $ccs = [];

    private array $bccs = [];

    private ?string $replyTo = null;

    private array $params = [];

    private array $attachmentUrls = [];

    private bool $hasOnDemand = false;

    /**
     * @throws \Throwable
     */
    public function __construct()
    {
        //
    }

    /**
     * @throws \Throwable
     */
    public function getContextName(): string
    {
        throw_if(empty($this->contextName), RavenInvalidDataException::class,
            'Notification context name is not set');

        return $this->contextName;
    }

    /**
     * @throws \Throwable
     */
    public function getRecipients(): array
    {
        throw_if(empty($this->recipients), RavenInvalidDataException::class,
            'Notification recipient is not set');

        return $this->recipients;
    }

    public function getCcs(): array
    {
        return $this->ccs;
    }

    public function getBccs(): array
    {
        return $this->bccs;
    }

    public function getReplyTo(): ?string
    {
        return $this->replyTo;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function getAttachmentUrls(): array
    {
        return $this->attachmentUrls;
    }

    public function getHasOnDemand(): bool
    {
        return $this->hasOnDemand;
    }

    public function setContextName(string $contextName): void
    {
        $this->contextName = $contextName;
    }

    /**
     * @throws \Throwable
     */
    public function setRecipients(object|string|array $recipients): void
    {
        if (is_array($recipients)) {

            foreach ($recipients as $recipient) {
                $this->validateRecipient($recipient);
            }
            $this->recipients = $recipients;
        } else {
            $this->validateRecipient($recipients);
            $this->recipients[] = $recipients;
        }
    }

    /**
     * @param  array<string, string>  $ccs
     */
    public function setCcs(array $ccs): void
    {
        $this->ccs = $ccs;
    }

    public function setBccs(array $bccs): void
    {
        $this->bccs = $bccs;
    }

    public function setReplyTo(string $replyTo): void
    {
        $this->replyTo = $replyTo;
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function setAttachmentUrls(string|array $attachmentUrls): void
    {
        if (is_array($attachmentUrls)) {
            $this->attachmentUrls = $attachmentUrls;
        } else {
            $this->attachmentUrls[] = $attachmentUrls;
        }
    }

    /**
     * @throws \Throwable
     */
    private function validateRecipient($recipient): void
    {
        if (is_null($recipient)) {
            throw new RavenInvalidDataException('Notification recipient cannot be null');
        }

        if (is_string($recipient)) {
            $this->hasOnDemand = true;

            return;
        }

        $notifiable = in_array(Notifiable::class, class_uses_recursive($recipient));
        throw_if(! $notifiable, RavenInvalidDataException::class,
            'Notification recipient is not a notifiable: add the Illuminate\Notifications\Notifiable trait to the recipient class');
    }
}
