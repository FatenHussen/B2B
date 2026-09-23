<?php

declare(strict_types=1);

namespace Modules\Notification\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Contracts\AppInbox;
use Modules\Core\Http\ApiController;
use Modules\Notification\Application\Actions\ClearInboxView;
use Modules\Notification\Application\Actions\MarkAllNotificationsRead;
use Modules\Notification\Application\Actions\RegisterPushToken;
use Modules\Notification\Application\Queries\ListAppNotifications;
use Modules\Notification\Presentation\Http\Requests\RegisterPushTokenRequest;

final class AppNotificationController extends ApiController
{
    public function index(Request $request, ListAppNotifications $query, AppInbox $inbox): JsonResponse
    {
        $user = $request->user();
        $kind = $query->kind($user);
        $page = $query($user, $request);

        return $this->paginated(
            $page,
            fn ($row) => $query->map($row),
            ['unread_count' => $inbox->unreadCount((int) $user->getAuthIdentifier(), $kind)],
        );
    }

    public function readAll(Request $request, MarkAllNotificationsRead $action): JsonResponse
    {
        return $this->ok($action($request->user()));
    }

    public function clear(Request $request, ClearInboxView $action): JsonResponse
    {
        return $this->ok($action($request->user()));
    }

    public function pushToken(RegisterPushTokenRequest $request, RegisterPushToken $action): JsonResponse
    {
        return $this->ok($action(
            $request->user(),
            $request->validated(),
            $request->header('X-Device-Id'),
        ));
    }
}
