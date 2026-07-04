<?php

namespace Tapao\LineNotification\Controllers;

use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * ConnectController
 *
 * Builds the LINE OAuth 2.0 authorization URL and redirects the user there.
 * State parameter is a signed token encoding the Flarum user ID.
 */
class ConnectController implements RequestHandlerInterface
{
    public function __construct(
        private readonly SettingsRepositoryInterface $settings
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        $channelId = $this->settings->get('tapao-line-notification.loginChannelId');

        if (empty($channelId)) {
            throw new \RuntimeException('LINE Login Channel ID is not configured.');
        }

        // Build a signed state token: base64(userId:hmac)
        $secret    = $this->settings->get('tapao-line-notification.loginChannelSecret', '');
        $state     = $this->buildState($actor->id, $secret);
        $callbackUrl = $this->buildCallbackUrl($request);

        $params = http_build_query([
            'response_type' => 'code',
            'client_id'     => $channelId,
            'redirect_uri'  => $callbackUrl,
            'state'         => $state,
            'scope'         => 'profile openid',
        ]);

        return new RedirectResponse('https://access.line.me/oauth2/v2.1/authorize?' . $params);
    }

    /**
     * Build a CSRF-safe state token: "<userId>.<hmac>".
     */
    private function buildState(int $userId, string $secret): string
    {
        $payload = $userId . '.' . time();
        $hmac    = hash_hmac('sha256', $payload, $secret);
        return base64_encode($payload . '.' . $hmac);
    }

    private function buildCallbackUrl(ServerRequestInterface $request): string
    {
        $uri    = $request->getUri();
        $scheme = $uri->getScheme();
        $host   = $uri->getHost();
        $port   = $uri->getPort();

        // Include port if non-standard
        $hostWithPort = $host;
        if ($port && !(($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80))) {
            $hostWithPort = $host . ':' . $port;
        }

        return sprintf('%s://%s/api/line/callback', $scheme, $hostWithPort);
    }
}
