<?php

namespace Tapao\LineNotification\Provider;

use Flarum\Foundation\AbstractServiceProvider;
use Tapao\LineNotification\Formatter\Resolver\DiscussionContentResolver;
use Tapao\LineNotification\Formatter\Resolver\PostContentResolver;
use Tapao\LineNotification\Formatter\Resolver\UserContentResolver;

/**
 * Binds the extendable list of content resolvers. Registered early (before
 * any third-party ->resolver() extender call) so container->extend() below
 * always has a base array to extend rather than failing on an unbound key.
 */
class LineNotificationServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->singleton('tapao-line-notification.resolvers', function () {
            return [
                PostContentResolver::class,
                DiscussionContentResolver::class,
                UserContentResolver::class,
            ];
        });
    }
}
