<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\HealthController;

Route::middleware('api')->prefix('api/v1')->group(function () {
    Route::get('health', HealthController::class);
});
