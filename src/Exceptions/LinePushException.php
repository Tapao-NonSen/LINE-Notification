<?php

namespace Tapao\LineNotification\Exceptions;

use RuntimeException;

/**
 * Thrown when LINE rejects a push with HTTP 400 (malformed payload) for a
 * reason unrelated to the recipient — e.g. an empty text component, an
 * unreachable hero image, or an over-length label. Unlike
 * LineUserNotFoundException, the driver must NOT clear the user's
 * line_user_id for this — the account is fine, the payload is not.
 */
class LinePushException extends RuntimeException
{
    public function __construct(string $lineUserId, private readonly string $responseBody)
    {
        parent::__construct("LINE rejected push to {$lineUserId}: {$responseBody}");
    }

    public function getResponseBody(): string
    {
        return $this->responseBody;
    }
}
