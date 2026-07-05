<?php

use Tapao\LineNotification\Controllers\CallbackController;
use Tapao\LineNotification\Controllers\ConnectController;
use Tapao\LineNotification\Controllers\DisconnectController;
use Tapao\LineNotification\Controllers\WebhookController;
use Tapao\LineNotification\Driver\LineNotificationDriver;
use Flarum\Extend;
use Flarum\Mentions\Notification\PostMentionedBlueprint;
use Flarum\Mentions\Notification\UserMentionedBlueprint;
use Flarum\Likes\Notification\PostLikedBlueprint;
use Flarum\Subscriptions\Notification\NewPostBlueprint;
use Flarum\Api\Resource\UserResource;
use Flarum\Api\Schema\Attribute;

return [
    // ──────────────── Assets / Frontend ────────────────
    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js')
        ->css(__DIR__ . '/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js')
        ->css(__DIR__ . '/less/admin.less')
        ->content(function (\Flarum\Frontend\Document $document) {
            $document->payload['lineNotificationTypes'] = array_keys(LineNotificationDriver::$registeredTypes);
        }),

    new Extend\Locales(__DIR__ . '/locale'),

    // ──────────────── Admin Settings ────────────────
    (new Extend\Settings())
        // LINE API credentials
        ->serializeToForum('tapao-line-notification.loginChannelId', 'tapao-line-notification.loginChannelId')
        ->serializeToForum('tapao-line-notification.loginChannelSecret', 'tapao-line-notification.loginChannelSecret')
        ->serializeToForum('tapao-line-notification.messagingAccessToken', 'tapao-line-notification.messagingAccessToken')
        ->serializeToForum('tapao-line-notification.messagingChannelSecret', 'tapao-line-notification.messagingChannelSecret')
        // Flex Message branding
        ->serializeToForum('tapao-line-notification.flexHeaderColor', 'tapao-line-notification.flexHeaderColor')
        ->serializeToForum('tapao-line-notification.flexButtonColor', 'tapao-line-notification.flexButtonColor')
        ->serializeToForum('tapao-line-notification.flexTitleColor', 'tapao-line-notification.flexTitleColor'),

    // ──────────────── API Routes ────────────────
    (new Extend\Routes('api'))
        ->get('/line/connect', 'line.connect', ConnectController::class)
        ->get('/line/callback', 'line.callback', CallbackController::class)
        ->delete('/line/disconnect', 'line.disconnect', DisconnectController::class)
        ->post('/line/webhook', 'line.webhook', WebhookController::class),

    // ──────────────── Notification Driver ────────────────
    (new Extend\Notification())
        ->driver('line', LineNotificationDriver::class, [
            PostMentionedBlueprint::class,
            UserMentionedBlueprint::class,
            PostLikedBlueprint::class,
            NewPostBlueprint::class,
        ]),

    // ──────────────── User Model — date casts ────────────────
    (new Extend\Model(\Flarum\User\User::class))
        ->cast('line_linked_at', 'datetime'),

    // ──────────────── User Serializer (expose LINE fields) ────────────────
    // Flarum 2.x — ApiResource extender
    (new Extend\ApiResource(UserResource::class))
        ->fields(fn () => [
            Attribute::make('lineUserId')
                ->get(fn ($user) => $user->line_user_id)
                ->writable(fn () => false),
            Attribute::make('lineDisplayName')
                ->get(fn ($user) => $user->line_display_name)
                ->writable(fn () => false),
            Attribute::make('lineLinkedAt')
                ->get(fn ($user) => $user->line_linked_at?->toIso8601String())
                ->writable(fn () => false),
        ]),

    // ──────────────── Database Migrations ────────────────
    new Extend\Migration(__DIR__ . '/migrations'),
];
