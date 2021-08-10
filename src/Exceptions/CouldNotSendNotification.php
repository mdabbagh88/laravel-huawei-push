<?php

namespace Alone\LaravelHuaweiPush\Exceptions;

use Alone\LaravelHuaweiPush\HuaweiMessage;
use Exception;

class CouldNotSendNotification extends Exception
{
    public static function invalidMessage(): CouldNotSendNotification
    {
        return new static('The toHuaweiPush() method only accepts instances of ' . HuaweiMessage::class);
    }
}
