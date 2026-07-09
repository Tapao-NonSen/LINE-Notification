<?php

namespace Tapao\LineNotification\Formatter\Resolver;

use Flarum\Discussion\Discussion;
use Flarum\Http\UrlGenerator;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Illuminate\Contracts\Translation\Translator;
use Tapao\LineNotification\Formatter\NotificationContent;

/**
 * Resolves notifications whose subject is a Discussion.
 */
class DiscussionContentResolver implements ContentResolverInterface
{
    public function __construct(
        private readonly UrlGenerator $url,
        private readonly Translator   $translator,
    ) {}

    public function supports(BlueprintInterface $blueprint): bool
    {
        return $blueprint->getSubject() instanceof Discussion;
    }

    public function resolve(BlueprintInterface $blueprint): NotificationContent
    {
        /** @var Discussion $discussion */
        $discussion = $blueprint->getSubject();

        return $this->resolveFromDiscussion($discussion);
    }

    public function resolveFromDiscussion(Discussion $discussion): NotificationContent
    {
        $fallback = $this->translator->get('tapao-line-notification.lib.line_message.fallback_title');

        return new NotificationContent(
            title: $discussion->title ?? $fallback,
            url:   $discussion->id
                ? $this->url->to('forum')->route('discussion', ['id' => $discussion->id])
                : $this->url->to('forum')->base(),
        );
    }
}
