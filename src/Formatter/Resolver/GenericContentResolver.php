<?php

namespace Tapao\LineNotification\Formatter\Resolver;

use Flarum\Discussion\Discussion;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Post\Post;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Database\Eloquent\Model;
use Tapao\LineNotification\Formatter\NotificationContent;

/**
 * GenericContentResolver
 *
 * Terminal resolver — always supports() and never fails. Handles any
 * blueprint from a third-party extension that no other resolver claimed,
 * by looking for a discussion/post relation on the subject, then a
 * title-like attribute, then falling back to a "from {user}" line so the
 * message body is never blank.
 */
class GenericContentResolver implements ContentResolverInterface
{
    public function __construct(
        private readonly Translator                $translator,
        private readonly PostContentResolver        $postResolver,
        private readonly DiscussionContentResolver  $discussionResolver,
    ) {}

    public function supports(BlueprintInterface $blueprint): bool
    {
        return true;
    }

    public function resolve(BlueprintInterface $blueprint): NotificationContent
    {
        $subject = $blueprint->getSubject();

        if ($subject instanceof Model) {
            if ($content = $this->tryDiscussion($subject)) {
                return $content;
            }

            if ($content = $this->tryPost($subject)) {
                return $content;
            }

            if ($content = $this->tryTitledAttribute($subject)) {
                return $content;
            }
        }

        return $this->fallback($blueprint);
    }

    private function tryDiscussion(Model $subject): ?NotificationContent
    {
        $discussion = $subject->getAttribute('discussion');

        return $discussion instanceof Discussion
            ? $this->discussionResolver->resolveFromDiscussion($discussion)
            : null;
    }

    private function tryPost(Model $subject): ?NotificationContent
    {
        $post = $subject->getAttribute('post');

        return $post instanceof Post
            ? $this->postResolver->resolveFromPost($post)
            : null;
    }

    private function tryTitledAttribute(Model $subject): ?NotificationContent
    {
        $title = $subject->getAttribute('title') ?: $subject->getAttribute('name');

        if (empty($title)) {
            return null;
        }

        return new NotificationContent(title: (string) $title);
    }

    private function fallback(BlueprintInterface $blueprint): NotificationContent
    {
        $fromUser = method_exists($blueprint, 'getFromUser') ? $blueprint->getFromUser() : null;

        $title   = $this->translator->get('tapao-line-notification.lib.line_message.fallback_title');
        $excerpt = $fromUser?->display_name
            ? $this->translator->get('tapao-line-notification.lib.line_message.notification_from_user', ['user' => $fromUser->display_name])
            : '';

        return new NotificationContent(title: $title, excerpt: $excerpt);
    }
}
