<?php

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\Integration\Presentation\Http\Controllers\PlatformSystemController;

Route::middleware(['api', 'auth:platform', 'guard.tokenable:platform', SubstituteBindings::class])
    ->prefix('api/v1/platform')
    ->group(function (): void {
        Route::get('system/queues', [PlatformSystemController::class, 'queues'])->middleware('permission:ad.system.view');
        Route::get('system/errors', [PlatformSystemController::class, 'errors'])->middleware('permission:ad.system.view');
        Route::get('system/sync', [PlatformSystemController::class, 'sync'])->middleware('permission:ad.system.view');
        Route::get('system/integrations', [PlatformSystemController::class, 'integrations'])->middleware('permission:ad.system.view');
        Route::post('system/switch-otp-channel', [PlatformSystemController::class, 'switchOtp'])->middleware('permission:ad.system.switch_otp');
        Route::post('system/retry-jobs', [PlatformSystemController::class, 'retryJobs'])->middleware('permission:ad.system.retry_jobs');
        Route::post('system/maintenance', [PlatformSystemController::class, 'maintenance'])->middleware('permission:ad.system.maintenance');
        Route::get('system/scheduled-jobs', [PlatformSystemController::class, 'scheduledJobs'])->middleware('permission:ad.system.view');
        Route::get('system/storage', [PlatformSystemController::class, 'storage'])->middleware('permission:ad.system.view');
        Route::post('system/backups', [PlatformSystemController::class, 'runBackup'])->middleware('permission:ad.system.backup');
        Route::post('system/backups/restore-test', [PlatformSystemController::class, 'restoreTest'])->middleware('permission:ad.system.backup');

        Route::get('settings/integrations', [PlatformSystemController::class, 'listIntegrations'])->middleware('permission:ad.settings.view');
        Route::put('settings/integrations/{provider}', [PlatformSystemController::class, 'saveIntegration'])->middleware('permission:ad.settings.update');
        Route::post('settings/integrations/{provider}/test', [PlatformSystemController::class, 'testIntegration'])->middleware('permission:ad.settings.update');
    });
