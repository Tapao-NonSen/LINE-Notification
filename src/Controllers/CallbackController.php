<?php

namespace Tapao\LineNotification\Controllers;

use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\UserRepository;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Carbon;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Tapao\LineNotification\Api\LineApiClient;

/**
 * CallbackController
 *
 * Handles the OAuth callback from LINE:
 *  1. Verifies the state parameter (CSRF + identifies the Flarum user).
 *  2. Exchanges the authorization code for an access token.
 *  3. Fetches the user's LINE profile.
 *  4. Persists line_user_id, line_display_name, line_linked_at to the user.
 *  5. Sends a confirmation push message to the user's LINE.
 *  6. Redirects back to /settings.
 */
class CallbackController implements RequestHandlerInterface
{
    public function __construct(
        private readonly SettingsRepositoryInterface $settings,
        private readonly UserRepository              $users,
        private readonly LineApiClient               $lineClient,
        private readonly LoggerInterface             $logger,
        private readonly UrlGenerator                $url,
        private readonly Translator                  $translator,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $code   = $params['code']  ?? null;
        $state  = $params['state'] ?? null;
        $error  = $params['error'] ?? null;

        // LINE may redirect back with an error (e.g. user denied consent)
        if ($error) {
            $errorDesc = $params['error_description'] ?? $error;
            return $this->errorRedirect($errorDesc);
        }

        if (!$code || !$state) {
            return $this->errorRedirect('Missing code or state parameter.');
        }

        // Verify state and extract userId
        $userId = $this->verifyState($state);
        if (!$userId) {
            return $this->errorRedirect('Invalid or expired state parameter.');
        }

        $user = $this->users->findOrFail($userId);

        $callbackUrl   = $this->buildCallbackUrl($request);
        $channelId     = $this->settings->get('tapao-line-notification.loginChannelId');
        $channelSecret = $this->settings->get('tapao-line-notification.loginChannelSecret');

        // Exchange code for access token
        $tokenResponse = $this->lineClient->issueAccessToken($code, $channelId, $channelSecret, $callbackUrl);

        if (!isset($tokenResponse['access_token'])) {
            return $this->errorRedirect('Failed to obtain LINE access token.');
        }

        // Fetch LINE profile
        $profile = $this->lineClient->getProfile($tokenResponse['access_token']);

        if (!isset($profile['userId'])) {
            return $this->errorRedirect('Failed to fetch LINE profile.');
        }

        // Persist to user
        $user->line_user_id      = $profile['userId'];
        $user->line_display_name = $profile['displayName'] ?? null;
        $user->line_linked_at    = Carbon::now();
        $user->save();

        // Send confirmation push message to LINE
        $this->sendConnectionConfirmation($profile['userId'], $profile['displayName'] ?? '', $user->display_name);

        // Build redirect back to settings with the forum's base URL
        $baseUrl = $this->getForumBaseUrl($request);
        return new RedirectResponse($baseUrl . '/settings?line_linked=1');
    }

    /**
     * Send a Flex Message to the user confirming their LINE account is now connected.
     * All text uses translation keys so any forum can customize the language.
     */
    private function sendConnectionConfirmation(string $lineUserId, string $lineDisplayName, string $forumUsername): void
    {
        $accessToken = $this->settings->get('tapao-line-notification.messagingAccessToken');

        if (empty($accessToken)) {
            $this->logger->warning('[LINE] Cannot send connection confirmation — Messaging API token not configured.');
            return;
        }

        $forumTitle = $this->settings->get('forum_title', 'Forum');
        $forumUrl   = $this->url->to('forum')->base();

        // Build translated text strings
        $t = fn (string $key, array $params = []) => $this->translator->get("tapao-line-notification.lib.line_message.{$key}", $params);

        $headerText  = $t('connection_success_header');
        $greeting    = $lineDisplayName
            ? $t('connection_success_greeting_name', ['name' => $lineDisplayName])
            : $t('connection_success_greeting');
        $bodyText    = $t('connection_success_body', ['forum' => $forumTitle, 'username' => $forumUsername]);
        $featuresIntro = $t('connection_success_features_intro');
        $featuresList  = $t('connection_success_feature_mention') . "\n" .
                         $t('connection_success_feature_user_mention') . "\n" .
                         $t('connection_success_feature_like') . "\n" .
                         $t('connection_success_feature_new_post');
        $footerHint  = $t('connection_success_settings_hint');
        $buttonLabel = $t('connection_success_open_forum', ['forum' => $forumTitle]);
        $altText     = $t('connection_success_alt_text', ['forum' => $forumTitle]);

        // Truncate button label to LINE's 20-char limit
        if (mb_strlen($buttonLabel) > 20) {
            $buttonLabel = mb_substr($buttonLabel, 0, 19) . '…';
        }

        $headerColor = $this->settings->get('tapao-line-notification.flexHeaderColor') ?: '#06C755';
        $buttonColor = $this->settings->get('tapao-line-notification.flexButtonColor') ?: '#06C755';

        $messages = [
            [
                'type'    => 'flex',
                'altText' => $altText,
                'contents' => [
                    'type'   => 'bubble',
                    'header' => [
                        'type'            => 'box',
                        'layout'          => 'vertical',
                        'backgroundColor' => $headerColor,
                        'contents'        => [
                            [
                                'type'   => 'text',
                                'text'   => $headerText,
                                'color'  => '#FFFFFF',
                                'size'   => 'lg',
                                'weight' => 'bold',
                            ],
                        ],
                    ],
                    'body' => [
                        'type'    => 'box',
                        'layout'  => 'vertical',
                        'spacing' => 'md',
                        'contents' => [
                            [
                                'type'   => 'text',
                                'text'   => $greeting,
                                'size'   => 'md',
                                'weight' => 'bold',
                                'wrap'   => true,
                            ],
                            [
                                'type'  => 'text',
                                'text'  => $bodyText,
                                'size'  => 'sm',
                                'color' => '#555555',
                                'wrap'  => true,
                            ],
                            [
                                'type' => 'separator',
                            ],
                            [
                                'type'  => 'text',
                                'text'  => $featuresIntro,
                                'size'  => 'sm',
                                'color' => '#555555',
                                'wrap'  => true,
                            ],
                            [
                                'type'  => 'text',
                                'text'  => $featuresList,
                                'size'  => 'sm',
                                'color' => '#333333',
                                'wrap'  => true,
                            ],
                            [
                                'type'   => 'text',
                                'text'   => $footerHint,
                                'size'   => 'xs',
                                'color'  => '#888888',
                                'wrap'   => true,
                                'margin' => 'md',
                            ],
                        ],
                    ],
                    'footer' => [
                        'type'    => 'box',
                        'layout'  => 'vertical',
                        'contents' => [
                            [
                                'type'   => 'button',
                                'style'  => 'primary',
                                'color'  => $buttonColor,
                                'action' => [
                                    'type'  => 'uri',
                                    'label' => $buttonLabel,
                                    'uri'   => $forumUrl,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        try {
            $this->lineClient->pushMessage($lineUserId, $messages, $accessToken);
            $this->logger->info('[LINE] Sent connection confirmation to LINE user ' . $lineUserId);
        } catch (\Throwable $e) {
            // Don't fail the connection flow if the confirmation message fails
            $this->logger->warning('[LINE] Failed to send connection confirmation: ' . $e->getMessage());
        }
    }

    /**
     * Verify the signed state token and return the user ID it contains.
     * Returns null on failure.
     */
    private function verifyState(string $encodedState): ?int
    {
        $decoded = base64_decode($encodedState, true);
        if ($decoded === false) {
            return null;
        }

        // Format: "<userId>.<timestamp>.<hmac>"
        $parts = explode('.', $decoded, 3);
        if (count($parts) !== 3) {
            return null;
        }

        [$userId, $timestamp, $hmac] = $parts;

        // State expires after 15 minutes
        if ((time() - (int) $timestamp) > 900) {
            return null;
        }

        $secret   = $this->settings->get('tapao-line-notification.loginChannelSecret', '');
        $payload  = $userId . '.' . $timestamp;
        $expected = hash_hmac('sha256', $payload, $secret);

        if (!hash_equals($expected, $hmac)) {
            return null;
        }

        return (int) $userId;
    }

    /**
     * Build the callback URL, including port if non-standard.
     */
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

    /**
     * Get the forum's base URL (scheme + host + port).
     */
    private function getForumBaseUrl(ServerRequestInterface $request): string
    {
        $uri    = $request->getUri();
        $scheme = $uri->getScheme();
        $host   = $uri->getHost();
        $port   = $uri->getPort();

        $hostWithPort = $host;
        if ($port && !(($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80))) {
            $hostWithPort = $host . ':' . $port;
        }

        return sprintf('%s://%s', $scheme, $hostWithPort);
    }

    private function errorRedirect(string $message): ResponseInterface
    {
        $msg = urlencode($message);
        return new RedirectResponse('/settings?line_error=' . $msg);
    }
}
