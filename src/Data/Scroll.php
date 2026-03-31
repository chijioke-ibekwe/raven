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

    /**
     * Create a new Scroll instance.
     */
    public static function make(): self
    {
        return new self;
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

    /**
     * Set the notification context name. Must match a key in the notification-contexts config.
     */
    public function for(string $contextName): self
    {
        $this->contextName = $contextName;

        return $this;
    }

    /**
     * Set the notification recipient(s). Accepts a notifiable, an email/phone string, or an array of either.
     *
     * @throws \Throwable
     */
    public function to(object|string|array $recipients): self
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

        return $this;
    }

    /**
     * Set CC recipients for email notifications.
     *
     * @param  array<string, string>  $ccs  Email addresses as keys, names as values
     */
    public function cc(array $ccs): self
    {
        $this->ccs = $ccs;

        return $this;
    }

    /**
     * Set BCC recipients for email notifications.
     *
     * @param  array<string, string>  $bccs  Email addresses as keys, names as values
     */
    public function bcc(array $bccs): self
    {
        $this->bccs = $bccs;

        return $this;
    }

    /**
     * Set the reply-to email address for email notifications.
     */
    public function replyTo(string $replyTo): self
    {
        $this->replyTo = $replyTo;

        return $this;
    }

    /**
     * Set the template parameters. Keys must match the placeholder names in the template.
     *
     * @param  array<string, mixed>  $params
     */
    public function with(array $params): self
    {
        $this->params = $params;

        return $this;
    }

    /**
     * Attach files to email notifications by URL.
     *
     * @param  string|string[]  $attachmentUrls  Publicly accessible URL(s)
     */
    public function attach(string|array $attachmentUrls): self
    {
        if (is_array($attachmentUrls)) {
            $this->attachmentUrls = $attachmentUrls;
        } else {
            $this->attachmentUrls[] = $attachmentUrls;
        }

        return $this;
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
            return;
        }

        $notifiable = in_array(Notifiable::class, class_uses_recursive($recipient));
        throw_if(! $notifiable, RavenInvalidDataException::class,
            'Notification recipient is not a notifiable: add the Illuminate\Notifications\Notifiable trait to the recipient class');
    }
}
