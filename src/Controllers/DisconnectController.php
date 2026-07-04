<?php

namespace Tapao\LineNotification\Controllers;

use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * DisconnectController
 *
 * Nulls out line_user_id, line_display_name, and line_linked_at
 * for the currently authenticated user.
 */
class DisconnectController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        $actor->line_user_id      = null;
        $actor->line_display_name = null;
        $actor->line_linked_at    = null;
        $actor->save();

        return new JsonResponse(['data' => ['type' => 'line-disconnect', 'attributes' => ['success' => true]]]);
    }
}
