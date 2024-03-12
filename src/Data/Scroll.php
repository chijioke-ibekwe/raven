<?php

namespace ChijiokeIbekwe\Raven\Data;

use Illuminate\Notifications\Notifiable;
use ChijiokeIbekwe\Raven\Exceptions\RavenInvalidDataException;

class Scroll
{

    /**
     * @var string
     */
    private string $contextName;

    /**
     * @var array
     */
    private array $recipients;

    /**
     * @var array
     */
    private array $ccs;

    /**
     * @var array
     */
    private array $params;

    /**
     * @var array
     */
    private array $attachmentUrls;

    /**
     * @var bool
     */
    private bool $hasOnDemand = false;

    /**
     *
     * @throws \Throwable
     */
    public function __construct(){
        //
    }

    /**
     * @return string
     * @throws \Throwable
     */
    public function getContextName(): string
    {
        throw_if(empty($this->contextName), RavenInvalidDataException::class,
            'Notification context name is not set');

        return $this->contextName;
    }

    /**
     * @return array
     * @throws \Throwable
     */
    public function getRecipients(): array
    {
        throw_if(empty($this->recipients), RavenInvalidDataException::class,
            'Notification recipient is not set');

        return $this->recipients;
    }

    /**
     * @return array
     */
    public function getCcs(): array
    {
        return $this->ccs;
    }

    /**
     * @return array
     */
    public function getParams(): array
    {
        return $this->params ?? [];
    }

    /**
     * @return array
     */
    public function getAttachmentUrls(): array
    {
        return $this->attachmentUrls ?? [];
    }

    
    /**
     * @return bool
     */
    public function getHasOnDemand(): bool
    {
        return $this->hasOnDemand;
    }

    /**
     * @param string $contextName
     * @return void
     */
    public function setContextName(string $contextName): void
    {
        $this->contextName = $contextName;
    }

    /**
     * @param mixed $recipients
     * @return void
     * @throws \Throwable
     */
    public function setRecipients(object|string|array $recipients): void
    {
        if(is_array($recipients)){

            foreach ($recipients as $recipient){
                $this->validateRecipient($recipient);
            }
            $this->recipients = $recipients;
        } else {
            $this->validateRecipient($recipients);
            $this->recipients[] = $recipients;
        }
    }

    /**
     * @param array<string, string> $ccs
     * @return void
     * @throws \Throwable
     */
    public function setCcs(array $ccs): void
    {
        $this->ccs = $ccs;
    }

    /**
     * @param array $params
     * @return void
     */
    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    /**
     * @param mixed $attachmentUrls
     * @return void
     */
    public function setAttachmentUrls(mixed $attachmentUrls): void
    {
        if(is_array($attachmentUrls)){
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
        if(is_null($recipient)) {
            throw new RavenInvalidDataException('Notification recipient cannot be null');
        }

        if(gettype($recipient) === 'string') {
            $this->hasOnDemand = true;
            return;
        }

        $notifiable = in_array(Notifiable::class, class_uses_recursive($recipient));
        throw_if(!$notifiable, RavenInvalidDataException::class, "Notification recipient is not a notifiable");
    }
}