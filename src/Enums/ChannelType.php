<?php

namespace ChijiokeIbekwe\Raven\Enums;

enum ChannelType: string {

    case EMAIL = 'EMAIL';
    case SMS = 'SMS';
    case DATABASE = 'DATABASE';
}