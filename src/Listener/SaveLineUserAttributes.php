<?php

namespace Tapao\LineNotification\Listener;

use Flarum\User\Event\Saving;

class SaveLineUserAttributes
{
    /**
     * Handle clearing LINE attributes when explicitly set to null via API.
     * (Normal saves happen through the controllers; this catches edge cases.)
     */
    public function handle(Saving $event): void
    {
        // Intentionally empty: LINE attributes are only written by
        // ConnectController and cleared by DisconnectController.
        // This listener is registered as a placeholder for future
        // user-data-save hooks if needed.
    }
}
