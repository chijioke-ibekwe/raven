<?php

namespace ChijiokeIbekwe\Raven\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use ChijiokeIbekwe\Raven\Enums\ChannelType;

class NotificationChannel extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $casts = [
        'type' => ChannelType::class
    ];

    public function notification_contexts(): BelongsToMany
    {
        return $this->belongsToMany(NotificationContext::class, 'notification_channel_notification_context',
            'notification_channel_id', 'notification_context_id');
    }
}