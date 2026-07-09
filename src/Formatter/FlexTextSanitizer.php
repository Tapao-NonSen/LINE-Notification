<?php

namespace Tapao\LineNotification\Formatter;

/**
 * FlexTextSanitizer
 *
 * LINE rejects a Flex Message with HTTP 400 if any "text" component is
 * empty, if altText/labels exceed their length limits, or if a hero image
 * is not a reachable https:// image URL. Centralizing those rules here
 * means a malformed payload never gets pushed (and can never look like
 * an invalid/blocked user and trigger an account unlink).
 */
final class FlexTextSanitizer
{
    public static function text(?string $value, int $maxLength = 2000): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $maxLength);
    }

    public static function label(?string $value, int $maxLength = 20): ?string
    {
        $value = self::text($value, 200);

        if ($value === null) {
            return null;
        }

        if (mb_strlen($value) > $maxLength) {
            return mb_substr($value, 0, $maxLength - 1) . '…';
        }

        return $value;
    }

    public static function altText(?string $value): string
    {
        return self::text($value, 400) ?? 'Notification';
    }

    /**
     * Only https:// jpg/jpeg/png URLs are accepted by LINE for hero images.
     */
    public static function imageUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        $parts = parse_url($url);

        if (($parts['scheme'] ?? '') !== 'https') {
            return null;
        }

        // Check the extension against the path only, so a query string
        // (e.g. a resizing CDN's "?w=800") doesn't disqualify the URL.
        if (!preg_match('/\.(jpe?g|png)$/i', $parts['path'] ?? '')) {
            return null;
        }

        return $url;
    }
}
