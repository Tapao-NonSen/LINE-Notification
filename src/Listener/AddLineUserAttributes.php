<?php

namespace Tapao\LineNotification\Listener;

use Flarum\Api\Serializer\CurrentUserSerializer;
use Flarum\User\User;

class AddLineUserAttributes
{
    /**
     * @param  CurrentUserSerializer  $serializer
     * @param  User  $user
     * @param  array  $attributes
     * @return array
     */
    public function __invoke(CurrentUserSerializer $serializer, User $user, array $attributes): array
    {
        $actor = $serializer->getActor();

        // Only expose LINE fields to the user themselves or admins
        if ($actor->id !== $user->id && !$actor->isAdmin()) {
            return $attributes;
        }

        $attributes['lineUserId']      = $user->line_user_id;
        $attributes['lineDisplayName'] = $user->line_display_name;
        $attributes['lineLinkedAt']    = $user->line_linked_at ? $user->line_linked_at->toIso8601String() : null;

        return $attributes;
    }
}
