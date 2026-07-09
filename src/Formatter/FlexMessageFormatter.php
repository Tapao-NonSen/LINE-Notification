<?php

namespace Tapao\LineNotification\Formatter;

use Flarum\Http\UrlGenerator;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Translation\Translator;
use Tapao\LineNotification\Formatter\Resolver\ContentResolverInterface;
use Tapao\LineNotification\Formatter\Resolver\GenericContentResolver;

/**
 * FlexMessageFormatter
 *
 * Converts a Flarum notification blueprint into a LINE Flex Message
 * (Bubble container) with:
 *  - Header: notification type label (translated)
 *  - Body: title + excerpt, resolved via a chain of ContentResolverInterface
 *          implementations so third-party blueprints render real content
 *          instead of a blank fallback
 *  - Footer: CTA button deep-linking to the resolved URL
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

    /** @var ContentResolverInterface[]|null */
    private ?array $resolvers = null;

    public function __construct(
        private readonly SettingsRepositoryInterface $settings,
        private readonly UrlGenerator                $url,
        private readonly Translator                  $translator,
        private readonly Container                   $container,
    ) {}

    /**
     * Format a notification blueprint into an array of LINE message objects.
     *
     * @return array  Array of LINE message objects (always one Flex Message)
     */
    public function format(BlueprintInterface $blueprint): array
    {
        $type    = $blueprint::getType();
        $content = $this->resolveContent($blueprint);

        $header = $this->resolveHeaderLabel($type);

        $buttonLabel = FlexTextSanitizer::label(
            $this->trans('view_post_button')
        ) ?? 'View';

        return [$this->buildFlexMessage($header, $content, $buttonLabel)];
    }

    // ──────────────── Resolver chain ────────────────

    private function resolveContent(BlueprintInterface $blueprint): NotificationContent
    {
        foreach ($this->resolvers() as $resolver) {
            if ($resolver->supports($blueprint)) {
                return $resolver->resolve($blueprint);
            }
        }

        // Unreachable in practice: GenericContentResolver::supports() always
        // returns true and is always appended last.
        return new NotificationContent(title: $this->trans('fallback_title'));
    }

    /**
     * @return ContentResolverInterface[]
     */
    private function resolvers(): array
    {
        if ($this->resolvers !== null) {
            return $this->resolvers;
        }

        /** @var class-string[] $classes */
        $classes = $this->container->make('tapao-line-notification.resolvers');

        $resolvers = array_map(
            fn (string $class) => $this->container->make($class),
            $classes
        );

        // Always terminal, regardless of registration order.
        $resolvers[] = $this->container->make(GenericContentResolver::class);

        return $this->resolvers = $resolvers;
    }

    // ──────────────── Header label ────────────────

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

    private function buildFlexMessage(string $header, NotificationContent $content, string $buttonLabel): array
    {
        $title   = FlexTextSanitizer::text($content->title, 200);
        $excerpt = FlexTextSanitizer::text($content->excerpt, 300);
        $url     = $content->url ?: $this->url->to('forum')->base();

        $bodyContents = [];

        if ($title !== null) {
            $bodyContents[] = [
                'type'   => 'text',
                'text'   => $title,
                'weight' => 'bold',
                'size'   => 'md',
                'color'  => $this->titleColor(),
                'wrap'   => true,
            ];
        }

        if ($excerpt !== null) {
            $bodyContents[] = [
                'type'   => 'text',
                'text'   => $excerpt,
                'size'   => 'sm',
                'color'  => '#555555',
                'wrap'   => true,
                'margin' => 'sm',
            ];
        }

        // A Flex Message body must not be empty — LINE returns 400 otherwise.
        if (empty($bodyContents)) {
            $bodyContents[] = [
                'type'  => 'text',
                'text'  => $header,
                'size'  => 'sm',
                'color' => '#555555',
                'wrap'  => true,
            ];
        }

        $bubble = [
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
        ];

        $imageUrl = FlexTextSanitizer::imageUrl($content->imageUrl);
        if ($imageUrl) {
            $bubble['hero'] = [
                'type'        => 'image',
                'url'         => $imageUrl,
                'size'        => 'full',
                'aspectRatio' => '20:13',
                'aspectMode'  => 'cover',
            ];
        }

        return [
            'type'     => 'flex',
            'altText'  => FlexTextSanitizer::altText($header . ': ' . ($title ?? '')),
            'contents' => $bubble,
        ];
    }

    // ──────────────── Translation helper ────────────────

    private function trans(string $key, array $params = []): string
    {
        return $this->translator->get("tapao-line-notification.lib.line_message.{$key}", $params);
    }
}
