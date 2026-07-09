# tapao/line-notification

A Flarum extension that lets forum users connect their LINE account and receive forum notifications (mentions, replies, likes, subscriptions) via LINE push messages.

---

## Features

- 🟢 **LINE OAuth connect/disconnect** — users link their LINE account from Settings
- 🔔 **LINE push notifications** — mention, reply, like, new-post notifications delivered as **LINE Flex Messages**
- 🎨 **Custom branding** — rich Flex Message cards with the forum's color scheme
- ⚙️ **Admin settings** — Channel ID, Channel Secret, Messaging API Token configurable from Flarum Admin
- 🛡️ **Auto-cleanup** — if a user blocks the LINE bot, their LINE data is automatically cleared
- 🔗 **Webhook endpoint** — handles LINE unfollow/block events

---

## Installation

### For Flarum 2.x (Recommended)
```bash
composer require tapao/line-notification
php flarum migrate
php flarum cache:clear
```

### For Flarum 1.x
```bash
composer require tapao/line-notification:^1.1
php flarum migrate
php flarum cache:clear
```

---

## Setup

### 1. LINE Developers Console

Create two channels on [LINE Developers Console](https://developers.line.biz/):

| Channel Type | Purpose |
|---|---|
| **LINE Login** | OAuth flow to link user accounts |
| **Messaging API** | Push message delivery |

### 2. LINE Login Channel settings

- Add callback URL: `https://YOUR-FORUM-DOMAIN/api/line/callback`
- Enable: `profile` and `openid` scopes

### 3. Messaging API Channel settings

- Register webhook URL: `https://YOUR-FORUM-DOMAIN/api/line/webhook`
- Issue a **Long-lived Channel Access Token**

### 4. Admin panel

Go to **Admin → Extensions → LINE Notification** and enter:
- LINE Login Channel ID
- LINE Login Channel Secret
- Messaging API Channel Access Token

---

## File Structure

```
tapao/line-notification/
├── composer.json
├── extend.php                          # Extension entry point
├── migrations/
│   └── 2024_01_01_000001_add_line_fields_to_users.php
├── src/
│   ├── Api/
│   │   └── LineApiClient.php           # HTTP wrapper for LINE APIs
│   ├── Controllers/
│   │   ├── ConnectController.php       # Redirects to LINE OAuth
│   │   ├── CallbackController.php      # Handles OAuth callback
│   │   ├── DisconnectController.php    # Clears LINE user data
│   │   └── WebhookController.php      # Handles LINE webhook events
│   ├── Driver/
│   │   └── LineNotificationDriver.php  # Flarum notification driver
│   ├── Exceptions/
│   │   ├── LineUserNotFoundException.php
│   │   └── LinePushException.php       # 400 (bad payload) ≠ unlink
│   ├── Extend/
│   │   └── LineNotification.php        # ->resolver() extender for other extensions
│   ├── Formatter/
│   │   ├── FlexMessageFormatter.php    # Builds LINE Flex Messages
│   │   ├── FlexTextSanitizer.php       # Guarantees a LINE-valid payload
│   │   ├── NotificationContent.php     # DTO returned by resolvers
│   │   └── Resolver/                   # Post/Discussion/User/Generic content resolvers
│   ├── Provider/
│   │   └── LineNotificationServiceProvider.php
│   └── Listener/
│       ├── AddLineUserAttributes.php   # Exposes LINE fields to API
│       └── SaveLineUserAttributes.php
├── js/
│   ├── package.json
│   ├── webpack.config.js
│   ├── tsconfig.json
│   └── src/
│       ├── forum/
│       │   ├── index.js                # Forum entry point
│       │   └── components/
│       │       └── LineAccountSection.js  # Connect/disconnect UI
│       └── admin/
│           └── index.js                # Admin settings entry point
├── less/
│   ├── forum.less                      # Forum styles
│   └── admin.less                      # Admin styles
└── locale/
    ├── en.yml
    └── th.yml
```

---

## Architecture

### Notification Flow

```
Flarum notification event
    → Extend\Notification()->type(Blueprint, ['line'])
    → LineNotificationDriver::send($blueprint, $users)
    → FlexMessageFormatter::format($blueprint)
        → first matching ContentResolverInterface (Post → Discussion → User → your resolvers → Generic)
        → FlexTextSanitizer (never-empty text, length caps, https-only hero image)
    → LineApiClient::pushMessage($lineUserId, $messages, $token)
    → LINE Messaging API
    → User's LINE app
```

### Rendering notifications from other extensions

Out of the box, `FlexMessageFormatter` renders `Post`, `Discussion`, and `User` notification subjects, and falls back to a generic resolver for anything else (it looks for a `discussion`/`post` relation or a `title`/`name` attribute, then a "From {user}" line — so the message is never blank).

If your extension's blueprint subject needs a more specific title, excerpt, or deep link, register your own resolver:

```php
// your-extension/extend.php
use Tapao\LineNotification\Extend\LineNotification;

return [
    (new LineNotification())
        ->resolver(\YourExtension\LineContentResolver::class),
];
```

```php
namespace YourExtension;

use Flarum\Notification\Blueprint\BlueprintInterface;
use Tapao\LineNotification\Formatter\NotificationContent;
use Tapao\LineNotification\Formatter\Resolver\ContentResolverInterface;

class LineContentResolver implements ContentResolverInterface
{
    public function supports(BlueprintInterface $blueprint): bool
    {
        return $blueprint->getSubject() instanceof YourModel;
    }

    public function resolve(BlueprintInterface $blueprint): NotificationContent
    {
        $subject = $blueprint->getSubject();

        return new NotificationContent(
            title: $subject->title,
            excerpt: $subject->summary,
            url: '/your-route/' . $subject->id,
        );
    }
}
```

Resolvers run in registration order — built-in Post → Discussion → User first, then resolvers registered via `->resolver()` in the order extensions load, and the generic fallback last regardless of registration order. Also add a `tapao-line-notification.lib.line_message.notification_<yourType>` translation key to give your notification type a custom header label — it's picked up automatically.

### OAuth Flow

```
User clicks "Connect LINE" in Settings
    → GET /api/line/connect
    → ConnectController: build authorize URL with signed state
    → Redirect to LINE OAuth
    → User grants consent
    → LINE redirects to GET /api/line/callback?code=...&state=...
    → CallbackController: verify state, exchange code, fetch profile
    → Write line_user_id, line_display_name, line_linked_at to user
    → Redirect to /settings?line_linked=1
```

---

## License

MIT
