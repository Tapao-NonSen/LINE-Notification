<?php

namespace Tapao\LineNotification\Formatter\Resolver;

use Flarum\Http\UrlGenerator;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Post\CommentPost;
use Flarum\Post\Post;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Translation\Translator;
use Tapao\LineNotification\Formatter\NotificationContent;

/**
 * Resolves notifications whose subject is a Post.
 */
class PostContentResolver implements ContentResolverInterface
{
    public function __construct(
        private readonly SettingsRepositoryInterface $settings,
        private readonly UrlGenerator                $url,
        private readonly Translator                  $translator,
    ) {}

    public function supports(BlueprintInterface $blueprint): bool
    {
        return $blueprint->getSubject() instanceof Post;
    }

    public function resolve(BlueprintInterface $blueprint): NotificationContent
    {
        /** @var Post $post */
        $post = $blueprint->getSubject();

        return $this->resolveFromPost($post);
    }

    public function resolveFromPost(Post $post): NotificationContent
    {
        $fallback = $this->translator->get('tapao-line-notification.lib.line_message.fallback_title');
        $title    = $post->discussion?->title ?? $fallback;

        $excerpt = '';
        if ($post instanceof CommentPost) {
            $content = strip_tags($post->formatContent());
            $content = html_entity_decode($content, ENT_QUOTES);
            $content = trim(preg_replace('/\s+/', ' ', $content));
            $excerpt = mb_substr($content, 0, 100) . (mb_strlen($content) > 100 ? '…' : '');
        }

        $url = $post->discussion_id
            ? $this->url->to('forum')->route('discussion', [
                'id'   => $post->discussion_id,
                'near' => $post->number,
            ])
            : $this->url->to('forum')->base();

        return new NotificationContent(
            title:    $title,
            excerpt:  $excerpt,
            url:      $url,
            imageUrl: $this->resolveImageUrl($post),
        );
    }

    private function resolveImageUrl(Post $post): ?string
    {
        if (!$post instanceof CommentPost) {
            return null;
        }

        if (!$this->settings->get('tapao-line-notification.useFirstImageAsThumbnail')) {
            return null;
        }

        $html = $post->formatContent();
        if (!preg_match('/<img[^>]+src="([^">]+)"/i', $html, $matches)) {
            return null;
        }

        $url = $matches[1];
        if (!preg_match('/^https?:\/\//i', $url)) {
            $baseUrl = rtrim($this->url->to('forum')->base(), '/');
            $url = $baseUrl . '/' . ltrim($url, '/');
        }

        return $url;
    }
}
