<?php

namespace ChijiokeIbekwe\Raven\Tests\Unit;

use Aws\Ses\SesClient;
use Aws\SesV2\SesV2Client;
use ChijiokeIbekwe\Raven\Channels\AmazonSesChannel;
use ChijiokeIbekwe\Raven\Channels\MailgunChannel;
use ChijiokeIbekwe\Raven\Channels\PostmarkChannel;
use ChijiokeIbekwe\Raven\Channels\SendGridChannel;
use ChijiokeIbekwe\Raven\Exceptions\RavenDeliveryException;
use ChijiokeIbekwe\Raven\Tests\TestCase;
use Illuminate\Notifications\Notification;
use Mailgun\Mailgun;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Postmark\PostmarkClient;
use SendGrid;

class EmailChannelContractTest extends TestCase
{
    public static function channels(): array
    {
        return [
            'sendgrid' => [SendGridChannel::class, 'SendGridChannel'],
            'ses' => [AmazonSesChannel::class, 'AmazonSesChannel'],
            'postmark' => [PostmarkChannel::class, 'PostmarkChannel'],
            'mailgun' => [MailgunChannel::class, 'MailgunChannel'],
        ];
    }

    #[DataProvider('channels')]
    public function test_throws_when_a_non_email_notification_is_passed(string $channelClass, string $channelLabel): void
    {
        $this->app->instance(SendGrid::class, Mockery::mock(SendGrid::class));
        $this->app->instance(SesClient::class, Mockery::mock(SesClient::class));
        $this->app->instance(SesV2Client::class, Mockery::mock(SesV2Client::class));
        $this->app->instance(PostmarkClient::class, Mockery::mock(PostmarkClient::class));
        $this->app->instance(Mailgun::class, Mockery::mock(Mailgun::class));

        $this->expectException(RavenDeliveryException::class);
        $this->expectExceptionMessage("$channelLabel requires an EmailNotification notification");

        $channel = new $channelClass;
        $channel->send(null, Mockery::mock(Notification::class));
    }
}
