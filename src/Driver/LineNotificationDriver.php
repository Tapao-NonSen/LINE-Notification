<?php

namespace Tapao\LineNotification\Driver;

use Flarum\Notification\Driver\NotificationDriverInterface;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Psr\Log\LoggerInterface;
use Tapao\LineNotification\Api\LineApiClient;
use Tapao\LineNotification\Exceptions\LineUserNotFoundException;
use Tapao\LineNotification\Formatter\FlexMessageFormatter;

/**
 * LineNotificationDriver
 *
 * Flarum notification driver that delivers notifications to users via
 * LINE Messaging API push messages.
 */
class LineNotificationDriver implements NotificationDriverInterface
{
    public static array $registeredTypes = [];

    public function __construct(
        private readonly LineApiClient               $lineClient,
        private readonly SettingsRepositoryInterface $settings,
        private readonly FlexMessageFormatter        $formatter,
        private readonly LoggerInterface             $logger,
    ) {}

    /**
     * Send the notification blueprint to each user who has LINE linked.
     */
    public function send(BlueprintInterface $blueprint, array $users): void
    {
        $type = $blueprint::getType();
        $disabledTypes = $this->settings->get('tapao-line-notification.disabledNotificationTypes');
        if ($disabledTypes) {
            $disabledArray = array_map('trim', explode(',', $disabledTypes));
            if (in_array($type, $disabledArray)) {
                return;
            }
        }

        $accessToken = $this->settings->get('tapao-line-notification.messagingAccessToken');

        if (empty($accessToken)) {
            $this->logger->warning('[LINE] Messaging API access token not configured — skipping push.');
            return;
        }

        /** @var User $user */
        foreach ($users as $user) {
            if (empty($user->line_user_id)) {
                continue;
            }

            try {
                $messages = $this->formatter->format($blueprint);
                $this->lineClient->pushMessage($user->line_user_id, $messages, $accessToken);
            } catch (LineUserNotFoundException $e) {
                // User blocked the bot or token is invalid — clear LINE data
                $this->logger->info('[LINE] Clearing LINE data for user ' . $user->id . ': ' . $e->getMessage());
                $user->line_user_id      = null;
                $user->line_display_name = null;
                $user->line_linked_at    = null;
                $user->save();
            } catch (\Throwable $e) {
                $this->logger->error('[LINE] Unexpected error sending to user ' . $user->id . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * Return the notification type keys this driver can handle.
     * Flarum uses this to build the notification preferences table columns.
     */
    public function getDefaultPreference(): bool
    {
        return false;
    }

    public function registerType(string $blueprintClass, array $driversEnabledByDefault): void
    {
        $type = $blueprintClass::getType();
        self::$registeredTypes[$type] = $blueprintClass;

        $disabledTypes = $this->settings->get('tapao-line-notification.disabledNotificationTypes');
        if ($disabledTypes) {
            $disabledArray = array_map('trim', explode(',', $disabledTypes));
            if (in_array($type, $disabledArray)) {
                return;
            }
        }

        $key = User::getNotificationPreferenceKey($type, 'line');
        $default = in_array('line', $driversEnabledByDefault);
    }
}

