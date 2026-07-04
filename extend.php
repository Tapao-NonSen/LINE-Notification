<?php

use Tapao\LineNotification\Controllers\CallbackController;
use Tapao\LineNotification\Controllers\ConnectController;
use Tapao\LineNotification\Controllers\DisconnectController;
use Tapao\LineNotification\Controllers\WebhookController;
use Tapao\LineNotification\Driver\LineNotificationDriver;
use Tapao\LineNotification\Listener\AddLineUserAttributes;
use Flarum\Extend;
use Flarum\Mentions\Notification\PostMentionedBlueprint;
use Flarum\Mentions\Notification\UserMentionedBlueprint;
use Flarum\Likes\Notification\PostLikedBlueprint;
use Flarum\Subscriptions\Notification\NewPostBlueprint;

$extenders = [
    // ──────────────── Assets / Frontend ────────────────
    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js')
        ->css(__DIR__ . '/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js')
        ->css(__DIR__ . '/less/admin.less'),

    new Extend\Locales(__DIR__ . '/locale'),

    // ──────────────── Admin Settings ────────────────
    (new Extend\Settings())
        // LINE API credentials
        ->serializeToForum('tapao-line-notification.loginChannelId', 'tapao-line-notification.loginChannelId')
        ->serializeToAdmin('tapao-line-notification.loginChannelSecret', 'tapao-line-notification.loginChannelSecret')
        ->serializeToAdmin('tapao-line-notification.messagingAccessToken', 'tapao-line-notification.messagingAccessToken')
        ->serializeToAdmin('tapao-line-notification.messagingChannelSecret', 'tapao-line-notification.messagingChannelSecret')
        // Flex Message branding
        ->serializeToAdmin('tapao-line-notification.flexHeaderColor', 'tapao-line-notification.flexHeaderColor')
        ->serializeToAdmin('tapao-line-notification.flexButtonColor', 'tapao-line-notification.flexButtonColor')
        ->serializeToAdmin('tapao-line-notification.flexTitleColor', 'tapao-line-notification.flexTitleColor'),

    // ──────────────── API Routes ────────────────
    (new Extend\Routes('api'))
        ->get('/line/connect', 'line.connect', ConnectController::class)
        ->get('/line/callback', 'line.callback', CallbackController::class)
        ->delete('/line/disconnect', 'line.disconnect', DisconnectController::class)
        ->post('/line/webhook', 'line.webhook', WebhookController::class),

    // ──────────────── Notification Driver ────────────────
    (new Extend\Notification())
        ->driver('line', LineNotificationDriver::class)
        ->type(PostMentionedBlueprint::class, ['line'])
        ->type(UserMentionedBlueprint::class, ['line'])
        ->type(PostLikedBlueprint::class, ['line'])
        ->type(NewPostBlueprint::class, ['line']),

    // ──────────────── User Model — date casts ────────────────
    (new Extend\Model(\Flarum\User\User::class))
        ->dateAttribute('line_linked_at'),
];

// ──────────────── User Serializer (expose LINE fields) ────────────────
// Flarum 2.x removed Extend\ApiSerializer → use Extend\ApiResource instead.
// Flarum 1.x has no Extend\ApiResource.
// We detect at runtime which to register.

if (class_exists(\Flarum\Api\Resource\UserResource::class)) {
    // Flarum 2.x — use ApiResource extender
    $extenders[] = (new Extend\ApiResource(\Flarum\Api\Resource\UserResource::class))
        ->fields(fn () => [
            \Flarum\Api\Schema\Attribute::make('lineUserId')
                ->get(fn ($user) => $user->line_user_id)
                ->writable(fn () => false),
            \Flarum\Api\Schema\Attribute::make('lineDisplayName')
                ->get(fn ($user) => $user->line_display_name)
                ->writable(fn () => false),
            \Flarum\Api\Schema\Attribute::make('lineLinkedAt')
                ->get(fn ($user) => $user->line_linked_at?->toIso8601String())
                ->writable(fn () => false),
        ]);
} else {
    // Flarum 1.x — use ApiSerializer extender
    $extenders[] = (new Extend\ApiSerializer(\Flarum\Api\Serializer\CurrentUserSerializer::class))
        ->attributes(AddLineUserAttributes::class);
}

// ──────────────── Database Migration (Flarum 2.x only) ────────────────
// Flarum 1.x auto-discovers from migrations/ directory.
// Flarum 2.x requires explicit Extend\Migration registration.
if (class_exists(Extend\Migration::class)) {
    $extenders[] = new Extend\Migration(__DIR__ . '/migrations');
}

return $extenders;
