<?php

namespace Tapao\LineNotification\Exceptions;

use RuntimeException;

/**
 * Thrown when LINE rejects a push because the user has blocked the bot
 * or their token is no longer valid. The driver should catch this and
 * clear the user's line_user_id.
 */
class LineUserNotFoundException extends RuntimeException
{
    public function __construct(string $lineUserId)
    {
        parent::__construct("LINE user {$lineUserId} is no longer reachable (blocked or invalid token).");
    }
}
