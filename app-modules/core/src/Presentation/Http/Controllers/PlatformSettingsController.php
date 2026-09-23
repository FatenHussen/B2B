<?php

declare(strict_types=1);

namespace Modules\Core\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Domain\Models\PlatformSetting;
use Modules\Core\Http\ApiController;

final class PlatformSettingsController extends ApiController
{
    /**
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function getGroup(string $group, array $defaults): array
    {
        $row = PlatformSetting::query()->where('group', $group)->where('key', 'config')->first();

        return array_merge($defaults, is_array($row?->value) ? $row->value : []);
    }

    /**
     * @return array<string, mixed>
     */
    private function putGroup(Request $request, string $group): array
    {
        $value = $request->all();
        PlatformSetting::query()->updateOrCreate(
            ['group' => $group, 'key' => 'config'],
            ['value' => $value, 'updated_by' => $request->user()?->getAuthIdentifier()],
        );

        return $value;
    }

    public function profile(): JsonResponse
    {
        return $this->ok($this->getGroup('profile', ['name' => 'B2B Platform', 'support_email' => 'support@example.com']));
    }

    public function updateProfile(Request $request): JsonResponse
    {
        return $this->ok($this->putGroup($request, 'profile'));
    }

    public function security(): JsonResponse
    {
        return $this->ok($this->getGroup('security', [
            'password_min_length' => 10,
            'require_2fa' => true,
            'session_timeout_minutes' => 60,
        ]));
    }

    public function updateSecurity(Request $request): JsonResponse
    {
        return $this->ok($this->putGroup($request, 'security'));
    }

    public function backup(): JsonResponse
    {
        return $this->ok($this->getGroup('backup', [
            'schedule' => '0 3 * * *',
            'destination' => 's3://b2b-backups',
            'retention_days' => 30,
            'encrypted' => true,
            'last_restore_test_at' => null,
            'last_restore_test_status' => null,
        ]));
    }

    public function updateBackup(Request $request): JsonResponse
    {
        return $this->ok($this->putGroup($request, 'backup'));
    }

    public function channelDefaults(): JsonResponse
    {
        return $this->ok($this->getGroup('channel_defaults', ['trial_days' => 14, 'default_plan_key' => 'starter']));
    }

    public function updateChannelDefaults(Request $request): JsonResponse
    {
        return $this->ok($this->putGroup($request, 'channel_defaults'));
    }
}
