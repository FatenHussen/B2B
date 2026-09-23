<?php

declare(strict_types=1);

namespace Modules\Content\Application\Queries;

use Illuminate\Http\Request;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;

final class ShowAppConfig
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(Request $request): array
    {
        $app = (string) $request->query('app', 'retailer');
        $platform = (string) $request->query('platform', 'android');
        $version = $this->version($request);
        $min = '1.0.0';
        $latest = '1.0.0';

        if ($version !== null && version_compare($version, $min, '<')) {
            throw DomainException::of(ErrorCode::UpgradeRequired);
        }

        $package = $app === 'rep' ? 'sy.b2b.rep' : 'sy.b2b.retailer';
        $store = $platform === 'ios'
            ? 'https://apps.apple.com/app/'.$package
            : 'https://play.google.com/store/apps/details?id='.$package;

        return [
            'min_supported_version' => $min,
            'latest_version' => $latest,
            'store_url' => $store,
            'force_update' => false,
            'release_notes' => '',
            'feature_flags' => [
                'offline_orders' => true,
                'loyalty' => true,
            ],
            'maintenance' => [
                'enabled' => false,
                'message_ar' => null,
                'until' => null,
            ],
        ];
    }

    private function version(Request $request): ?string
    {
        $raw = $request->query('version') ?: $request->header('X-App-Version');
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        if (preg_match('/^(\d+\.\d+\.\d+)/', $raw, $m) === 1) {
            return $m[1];
        }

        return $raw;
    }
}
