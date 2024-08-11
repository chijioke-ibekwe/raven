<?php

namespace ChijiokeIbekwe\Raven\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use ChijiokeIbekwe\Raven\Database\Factories\NotificationContextFactory;


/**
 * @property string $name
 * @property string $description
 * @property string $email_template_id
 * @property string $email_template_filename
 * @property string $sms_template_filename
 * @property string $in_app_template_filename
 * @property string $type
 * @property string $active
 */
class NotificationContext extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'description',
        'email_template_id',
        'email_template_filename',
        'sms_template_filename',
        'in_app_template_filename',
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