<?php

namespace Tapao\LineNotification\Extend;

use Flarum\Extend\ExtenderInterface;
use Illuminate\Contracts\Container\Container;

/**
 * Extend\LineNotification
 *
 * Lets other extensions teach the LINE Notification extension how to
 * render their own notification blueprints, instead of falling through
 * to the generic fallback.
 *
 * Usage (in another extension's extend.php):
 *
 *   (new \Tapao\LineNotification\Extend\LineNotification())
 *       ->resolver(\My\Extension\LineContentResolver::class),
 *
 * The resolver class must implement
 * \Tapao\LineNotification\Formatter\Resolver\ContentResolverInterface
 * and is instantiated through the container, so its constructor may
 * type-hint any bound service.
 */
class LineNotification implements ExtenderInterface
{
    /** @var class-string[] */
    private array $resolvers = [];

    public function resolver(string $resolverClass): self
    {
        $this->resolvers[] = $resolverClass;

        return $this;
    }

    public function extend(Container $container, ?\Flarum\Extension\Extension $extension = null): void
    {
        $resolvers = $this->resolvers;

        $container->extend(
            'tapao-line-notification.resolvers',
            function (array $existing) use ($resolvers) {
                return array_merge($existing, $resolvers);
            }
        );
    }
}
