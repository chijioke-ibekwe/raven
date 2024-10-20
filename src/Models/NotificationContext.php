<?php

namespace ChijiokeIbekwe\Raven\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
 * @property array $channels
 */
class NotificationContext extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'email_template_id',
        'email_template_filename',
        'sms_template_filename',
        'in_app_template_filename',
        'type',
        'active',
        'channels'
    ];

    protected $casts = [
        'active' => 'boolean',
        'channels' => 'array',
        'created_at' => 'datetime:Y-m-d H:i',
        'updated_at' => 'datetime:Y-m-d H:i'
    ];

    protected static function newFactory(): NotificationContextFactory
    {
        return NotificationContextFactory::new();
    }
}