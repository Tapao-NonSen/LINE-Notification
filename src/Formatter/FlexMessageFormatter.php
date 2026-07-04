<?php

namespace Tapao\LineNotification\Formatter;

use Flarum\Discussion\Discussion;
use Flarum\Http\UrlGenerator;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Post\Post;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Translation\Translator;

/**
 * FlexMessageFormatter
 *
 * Converts a Flarum notification blueprint into a LINE Flex Message
 * (Bubble container) with:
 *  - Header: notification type label (translated)
 *  - Body: discussion title + post excerpt
 *  - Footer: "View Post" button deep-linking to the forum post
 *
 * Colors are configurable via admin settings:
 *  - tapao-line-notification.flexHeaderColor  (header background)
 *  - tapao-line-notification.flexButtonColor  (CTA button)
 *  - tapao-line-notification.flexTitleColor   (title text in body)
 */
class FlexMessageFormatter
{
    private const DEFAULT_HEADER_COLOR = '#06C755'; // LINE Green
    private const DEFAULT_BUTTON_COLOR = '#06C755';
    private const DEFAULT_TITLE_COLOR  = '#111111';
    private const WHITE                = '#FFFFFF';

    public function __construct(
        private readonly SettingsRepositoryInterface $settings,
        private readonly UrlGenerator                $url,
        private readonly Translator                  $translator,
    ) {}

    /**
     * Format a notification blueprint into an array of LINE message objects.
     *
     * @return array  Array of LINE message objects (always one Flex Message)
     */
    public function format(BlueprintInterface $blueprint): array
    {
        $subject = $blueprint->getSubject();
        $type    = $blueprint->getType();

        $title   = $this->resolveTitle($subject);
        $excerpt = $this->resolveExcerpt($subject);
        $url     = $this->resolveUrl($subject);
        $header  = $this->resolveHeaderLabel($type);

        // Read button label from translations
        $buttonLabel = $this->trans('view_post_button');
        if (mb_strlen($buttonLabel) > 20) {
            $buttonLabel = mb_substr($buttonLabel, 0, 19) . '…';
        }

        return [$this->buildFlexMessage($header, $title, $excerpt, $url, $buttonLabel)];
    }

    // ──────────────── Resolution helpers ────────────────

    private function resolveTitle(mixed $subject): string
    {
        $fallback = $this->trans('fallback_title');

        if ($subject instanceof Post) {
            return $subject->discussion?->title ?? $fallback;
        }

        if ($subject instanceof Discussion) {
            return $subject->title ?? $fallback;
        }

        return $fallback;
    }

    private function resolveExcerpt(mixed $subject): string
    {
        if ($subject instanceof Post) {
            $content = strip_tags($subject->formatContent());
            return mb_substr($content, 0, 100) . (mb_strlen($content) > 100 ? '…' : '');
        }

        return '';
    }

    private function resolveUrl(mixed $subject): string
    {
        $baseUrl = $this->url->to('forum')->base();

        if ($subject instanceof Post) {
            return $baseUrl . '/d/' . $subject->discussion_id . '/' . $subject->number;
        }

        if ($subject instanceof Discussion) {
            return $baseUrl . '/d/' . $subject->id;
        }

        return $baseUrl;
    }

    private function resolveHeaderLabel(string $type): string
    {
        // Try a translation key specific to the notification type first,
        // then fall back to a generic header with the forum name.
        $key = "tapao-line-notification.lib.line_message.notification_{$type}";

        $translated = $this->translator->get($key);

        // If the translator returns the key itself, the key is missing → use default
        if ($translated === $key) {
            $forumTitle = $this->settings->get('forum_title', 'Forum');
            return $this->trans('notification_default', ['forum' => $forumTitle]);
        }

        return $translated;
    }

    // ──────────────── Colors from admin settings ────────────────

    private function headerColor(): string
    {
        return $this->settings->get('tapao-line-notification.flexHeaderColor') ?: self::DEFAULT_HEADER_COLOR;
    }

    private function buttonColor(): string
    {
        return $this->settings->get('tapao-line-notification.flexButtonColor') ?: self::DEFAULT_BUTTON_COLOR;
    }

    private function titleColor(): string
    {
        return $this->settings->get('tapao-line-notification.flexTitleColor') ?: self::DEFAULT_TITLE_COLOR;
    }

    // ──────────────── Flex Message builder ────────────────

    private function buildFlexMessage(string $header, string $title, string $excerpt, string $url, string $buttonLabel): array
    {
        $bodyContents = [
            [
                'type'   => 'text',
                'text'   => $title,
                'weight' => 'bold',
                'size'   => 'md',
                'color'  => $this->titleColor(),
                'wrap'   => true,
            ],
        ];

        if (!empty($excerpt)) {
            $bodyContents[] = [
                'type'   => 'text',
                'text'   => $excerpt,
                'size'   => 'sm',
                'color'  => '#555555',
                'wrap'   => true,
                'margin' => 'sm',
            ];
        }

        return [
            'type'    => 'flex',
            'altText' => $header . ': ' . $title,
            'contents' => [
                'type'   => 'bubble',
                'header' => [
                    'type'            => 'box',
                    'layout'          => 'vertical',
                    'backgroundColor' => $this->headerColor(),
                    'contents'        => [
                        [
                            'type'   => 'text',
                            'text'   => $header,
                            'color'  => self::WHITE,
                            'size'   => 'sm',
                            'weight' => 'bold',
                            'wrap'   => true,
                        ],
                    ],
                ],
                'body' => [
                    'type'     => 'box',
                    'layout'   => 'vertical',
                    'spacing'  => 'sm',
                    'contents' => $bodyContents,
                ],
                'footer' => [
                    'type'     => 'box',
                    'layout'   => 'vertical',
                    'contents' => [
                        [
                            'type'   => 'button',
                            'style'  => 'primary',
                            'color'  => $this->buttonColor(),
                            'action' => [
                                'type'  => 'uri',
                                'label' => $buttonLabel,
                                'uri'   => $url,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    // ──────────────── Translation helper ────────────────

    private function trans(string $key, array $params = []): string
    {
        return $this->translator->get("tapao-line-notification.lib.line_message.{$key}", $params);
    }
}
