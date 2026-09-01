<?php

declare(strict_types=1);

namespace Modules\Integration;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\OtpChannel;
use Modules\Integration\Otp\CompositeOtpChannel;
use Modules\Integration\Otp\SmsOtpChannel;
use Modules\Integration\Otp\WhatsAppOtpChannel;

class IntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SmsOtpChannel::class, SmsOtpChannel::class);
        $this->app->bind(WhatsAppOtpChannel::class, WhatsAppOtpChannel::class);

        $driver = (string) config('otp.channel', 'log');

        if (in_array($driver, ['whatsapp', 'composite'], true) && ! $this->app->environment('testing')) {
            $this->app->bind(OtpChannel::class, function ($app) {
                return new CompositeOtpChannel(
                    $app->make(WhatsAppOtpChannel::class),
                    $app->make(SmsOtpChannel::class),
                );
            });
        }
    }

    public function boot(): void {}
}
