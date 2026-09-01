<?php

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'auth:sanctum', 'tenant', SubstituteBindings::class])->prefix('api/v1')->group(function () {
    // Returns — filled in later sprints
});
