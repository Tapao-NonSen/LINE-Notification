<?php

namespace Tapao\LineNotification\Controllers;

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Laminas\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * WebhookController
 *
 * Handles incoming webhook events from LINE (Phase 5).
 * Currently handles:
 *  - "unfollow" events: clears line_user_id so no more pushes are attempted.
 *  - "block" events (treated same as unfollow).
 */
class WebhookController implements RequestHandlerInterface
{
    public function __construct(
        private readonly SettingsRepositoryInterface $settings
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body      = (string) $request->getBody();
        $signature = $request->getHeaderLine('X-Line-Signature');

        // Verify LINE webhook signature
        if (!$this->verifySignature($body, $signature)) {
            return new EmptyResponse(401);
        }

        $payload = json_decode($body, true);

        foreach ($payload['events'] ?? [] as $event) {
            if (in_array($event['type'], ['unfollow', 'block'], true)) {
                $lineUserId = $event['source']['userId'] ?? null;
                if ($lineUserId) {
                    $this->clearLineUser($lineUserId);
                }
            }
        }

        return new EmptyResponse(200);
    }

    /**
     * Verify the HMAC-SHA256 signature from LINE.
     */
    private function verifySignature(string $body, string $signature): bool
    {
        $secret   = $this->settings->get('tapao-line-notification.messagingChannelSecret', '');
        $expected = base64_encode(hash_hmac('sha256', $body, $secret, true));

        return hash_equals($expected, $signature);
    }

    /**
     * Clear LINE data for the user identified by the given LINE userId.
     */
    private function clearLineUser(string $lineUserId): void
    {
        $user = User::where('line_user_id', $lineUserId)->first();
        if ($user) {
            $user->line_user_id      = null;
            $user->line_display_name = null;
            $user->line_linked_at    = null;
            $user->save();
        }
    }
}
