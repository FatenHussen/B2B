<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\ApiController;
use Modules\Identity\Application\Actions\RegisterRep;
use Modules\Identity\Application\Actions\RegisterRetailer;
use Modules\Identity\Application\Queries\AppSession;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Presentation\Http\Requests\RegisterRepRequest;
use Modules\Identity\Presentation\Http\Requests\RegisterRetailerRequest;

final class AppAuthController extends ApiController
{
    public function registerRetailer(RegisterRetailerRequest $request, RegisterRetailer $action): JsonResponse
    {
        /** @var AppUser $user */
        $user = $request->user();

        return $this->created($action($user, $request->validated()));
    }

    public function registerRep(RegisterRepRequest $request, RegisterRep $action): JsonResponse
    {
        /** @var AppUser $user */
        $user = $request->user();

        return $this->created($action($user, $request->validated()));
    }

    public function session(Request $request, AppSession $query): JsonResponse
    {
        /** @var AppUser $user */
        $user = $request->user();

        return $this->ok($query($user));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return $this->ok(['success' => true]);
    }
}
