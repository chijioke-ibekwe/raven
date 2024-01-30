<?php

namespace ChijiokeIbekwe\Raven\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use ChijiokeIbekwe\Raven\Database\Factories\NotificationContextFactory;


/**
 * @property string $email_template_id
 * @property string $name
 * @property string $title
 * @property string $body
 * @property string $type
 */
class NotificationContext extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'email_template_id',
        'name',
        'description',
        'title',
        'body',
        'type',
        'active'
    ];

    public function notification_channels(): BelongsToMany
    {
        return $this->belongsToMany(NotificationChannel::class, 'notification_channel_notification_context',
        'notification_context_id', 'notification_channel_id');
    }

    protected static function newFactory(): NotificationContextFactory
    {
        return NotificationContextFactory::new();
    }
}