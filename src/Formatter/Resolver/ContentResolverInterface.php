<?php

namespace Tapao\LineNotification\Formatter\Resolver;

use Flarum\Notification\Blueprint\BlueprintInterface;
use Tapao\LineNotification\Formatter\NotificationContent;

/**
 * ContentResolverInterface
 *
 * Turns a notification blueprint into renderable content for a LINE Flex
 * Message. Register additional resolvers via Extend\LineNotification so
 * blueprints from other extensions render real titles/excerpts/links
 * instead of falling through to the generic fallback.
 */
interface ContentResolverInterface
{
    /**
     * Whether this resolver knows how to handle the given blueprint's subject.
     */
    public function supports(BlueprintInterface $blueprint): bool;

    /**
     * Resolve the blueprint into content. Only called when supports() is true.
     */
    public function resolve(BlueprintInterface $blueprint): NotificationContent;
}
