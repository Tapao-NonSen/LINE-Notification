<?php

namespace Tapao\LineNotification\Formatter;

/**
 * NotificationContent
 *
 * Resolver-agnostic description of what to render in a LINE Flex Message
 * for a given notification. Produced by a ContentResolverInterface and
 * consumed by FlexMessageFormatter.
 */
final class NotificationContent
{
    public function __construct(
        public readonly string  $title,
        public readonly string  $excerpt = '',
        public readonly ?string $url = null,
        public readonly ?string $imageUrl = null,
    ) {}
}
