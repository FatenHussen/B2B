<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Http\ApiController;
use Modules\Identity\Application\Actions\ResetChannelManager;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\PlatformInvite;
use Modules\Identity\Domain\Models\PlatformUser;

final class PlatformOpsController extends ApiController
{
    public function resetManager(Request $request, int $id, ResetChannelManager $action): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'invite_via' => ['sometimes', 'string', 'in:whatsapp,sms,email'],
        ]);

        /** @var object $actor */
        $actor = $request->user();

        return $this->ok($action($id, $data, $actor));
    }

    public function team(): JsonResponse
    {
        $rows = PlatformUser::query()->orderBy('id')->get()->map(fn (PlatformUser $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'roles' => $u->getRoleNames()->values()->all(),
            'status' => $u->status instanceof \BackedEnum ? $u->status->value : (string) $u->status,
        ]);

        return $this->ok($rows->all());
    }

    public function invite(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer'],
        ]);

        $invite = PlatformInvite::query()->create([
            'email' => $data['email'],
            'role_ids' => $data['role_ids'],
            'token_hash' => hash('sha256', Str::random(40)),
            'expires_at' => now()->addHours(72),
            'invited_by' => $request->user()?->getAuthIdentifier(),
        ]);

        return $this->created(['id' => $invite->id, 'expires_at' => $invite->expires_at->toIso8601String()]);
    }

    public function invites(): JsonResponse
    {
        $rows = PlatformInvite::query()
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->get()
            ->map(fn (PlatformInvite $i) => [
                'id' => $i->id,
                'email' => $i->email,
                'role_ids' => $i->role_ids,
                'expires_at' => $i->expires_at?->toIso8601String(),
            ]);

        return $this->ok($rows->all());
    }

    public function disable(Request $request, int $id): JsonResponse
    {
        $user = PlatformUser::query()->findOrFail($id);
        $this->assertNotLastAdmin($user);

        if ((int) $request->user()?->getAuthIdentifier() === (int) $user->id) {
            throw DomainException::of(ErrorCode::IllegalTransition, __('tenancy.illegal_transition'));
        }

        $user->status = UserStatus::Suspended;
        $user->save();

        return $this->ok(['id' => $user->id, 'status' => UserStatus::Suspended->value]);
    }

    public function updateMember(Request $request, int $id): JsonResponse
    {
        $user = PlatformUser::query()->findOrFail($id);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email'],
        ]);
        $user->fill($data)->save();

        return $this->ok(['id' => $user->id]);
    }

    public function deleteMember(Request $request, int $id): JsonResponse
    {
        $user = PlatformUser::query()->findOrFail($id);
        $this->assertNotLastAdmin($user);
        $user->delete();

        return $this->ok(['id' => $id, 'deleted' => true]);
    }

    private function assertNotLastAdmin(PlatformUser $user): void
    {
        if (! $user->hasRole('platform_admin')) {
            return;
        }

        $admins = PlatformUser::query()->role('platform_admin')->where('id', '!=', $user->id)->count();
        if ($admins < 1) {
            throw DomainException::of(ErrorCode::IllegalTransition, __('tenancy.illegal_transition'));
        }
    }
}
