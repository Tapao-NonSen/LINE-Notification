<?php

namespace Tapao\LineNotification\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Psr\Log\LoggerInterface;

/**
 * LineApiClient
 *
 * Thin wrapper around the LINE APIs:
 *  - LINE Login API  (token exchange, profile)
 *  - LINE Messaging API  (push message delivery)
 */
class LineApiClient
{
    private const TOKEN_ENDPOINT   = 'https://api.line.me/oauth2/v2.1/token';
    private const PROFILE_ENDPOINT = 'https://api.line.me/v2/profile';
    private const PUSH_ENDPOINT    = 'https://api.line.me/v2/bot/message/push';

    private Client $http;

    public function __construct(
        private readonly LoggerInterface $logger
    ) {
        $this->http = new Client(['timeout' => 10.0]);
    }

    // ──────────────── LINE Login ────────────────

    /**
     * Exchange an authorization code for an access token.
     */
    public function issueAccessToken(
        string $code,
        string $channelId,
        string $channelSecret,
        string $redirectUri
    ): array {
        try {
            $response = $this->http->post(self::TOKEN_ENDPOINT, [
                'form_params' => [
                    'grant_type'    => 'authorization_code',
                    'code'          => $code,
                    'redirect_uri'  => $redirectUri,
                    'client_id'     => $channelId,
                    'client_secret' => $channelSecret,
                ],
            ]);

            return json_decode((string) $response->getBody(), true) ?? [];
        } catch (\Throwable $e) {
            $this->logger->error('[LINE] Token exchange failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Fetch the LINE user's profile using an access token.
     */
    public function getProfile(string $accessToken): array
    {
        try {
            $response = $this->http->get(self::PROFILE_ENDPOINT, [
                'headers' => ['Authorization' => 'Bearer ' . $accessToken],
            ]);

            return json_decode((string) $response->getBody(), true) ?? [];
        } catch (\Throwable $e) {
            $this->logger->error('[LINE] Profile fetch failed: ' . $e->getMessage());
            return [];
        }
    }

    // ──────────────── Messaging API ────────────────

    /**
     * Push a message to a LINE user.
     *
     * @param  string  $lineUserId       Recipient's LINE userId
     * @param  array   $messages         Array of LINE message objects
     * @param  string  $accessToken      Messaging API channel access token
     * @return bool                      True on success, false on failure
     */
    public function pushMessage(string $lineUserId, array $messages, string $accessToken): bool
    {
        try {
            $this->http->post(self::PUSH_ENDPOINT, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'to'       => $lineUserId,
                    'messages' => $messages,
                ],
            ]);

            return true;
        } catch (ClientException $e) {
            $status = $e->getResponse()->getStatusCode();

            // 429: Rate limited — caller should back off
            if ($status === 429) {
                $this->logger->warning('[LINE] Push rate-limited for user ' . $lineUserId);
                return false;
            }

            // 400 / 403: Invalid token or user blocked bot — signal caller to clear line_user_id
            if (in_array($status, [400, 403], true)) {
                $this->logger->warning('[LINE] Push rejected (' . $status . ') for user ' . $lineUserId . ' — token may be invalid or user blocked the bot.');
                throw new \Tapao\LineNotification\Exceptions\LineUserNotFoundException($lineUserId);
            }

            $this->logger->error('[LINE] Push failed (' . $status . ') for user ' . $lineUserId . ': ' . $e->getMessage());
            return false;
        } catch (\Throwable $e) {
            $this->logger->error('[LINE] Push unexpected error for user ' . $lineUserId . ': ' . $e->getMessage());
            return false;
        }
    }
}
