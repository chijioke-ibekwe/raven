<?php

namespace ChijiokeIbekwe\Messenger\Data;

use Illuminate\Notifications\Notifiable;
use ChijiokeIbekwe\Messenger\Exceptions\MessengerInvalidDataException;

class NotificationData
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
    private array $attachments;

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
        throw_if(empty($this->contextName), MessengerInvalidDataException::class,
            'Notification context name is not set');

        return $this->contextName;
    }

    /**
     * @return array
     * @throws \Throwable
     */
    public function getRecipients(): array
    {
        throw_if(empty($this->recipients), MessengerInvalidDataException::class,
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
    public function getAttachments(): array
    {
        return $this->attachments ?? [];
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
    public function setRecipients(mixed $recipients): void
    {
        if(is_array($recipients)){
            foreach ($recipients as $recipient){
                $this->confirmRecipientIsNotifiable($recipient);
            }
            $this->recipients = $recipients;
        } else {
            $this->confirmRecipientIsNotifiable($recipients);
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
     * @param mixed $attachments
     * @return void
     */
    public function setAttachments(mixed $attachments): void
    {
        if(is_array($attachments)){
            $this->attachments = $attachments;
        } else {
            $this->attachments[] = $attachments;
        }
    }

    /**
     * @throws \Throwable
     */
    private function confirmRecipientIsNotifiable($recipient): void
    {
        $notifiable = in_array(Notifiable::class, class_uses_recursive($recipient));
        throw_if(!$notifiable, MessengerInvalidDataException::class,
            "Notification recipient is not notifiable");
    }
}