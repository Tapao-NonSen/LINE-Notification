<?php

namespace Tapao\LineNotification\Formatter\Resolver;

use Flarum\Http\UrlGenerator;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\User\User;
use Tapao\LineNotification\Formatter\NotificationContent;

/**
 * Resolves notifications whose subject is a User (e.g. flarum/suspend).
 */
class UserContentResolver implements ContentResolverInterface
{
    public function __construct(
        private readonly UrlGenerator $url,
    ) {}

    public function supports(BlueprintInterface $blueprint): bool
    {
        return $blueprint->getSubject() instanceof User;
    }

    public function resolve(BlueprintInterface $blueprint): NotificationContent
    {
        /** @var User $user */
        $user = $blueprint->getSubject();

        return $this->resolveFromUser($user);
    }

    public function resolveFromUser(User $user): NotificationContent
    {
        return new NotificationContent(
            title: $user->display_name ?? $user->username,
            url:   $user->username
                ? $this->url->to('forum')->route('user', ['username' => $user->username])
                : $this->url->to('forum')->base(),
        );
    }
}
