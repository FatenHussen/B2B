<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\Access\Presentation\Http\Controllers\AuditController;
use Modules\Access\Presentation\Http\Controllers\IamController;

Route::middleware(['api', SubstituteBindings::class, 'auth:platform', 'guard.tokenable:platform'])
    ->prefix('api/v1/platform')
    ->group(function (): void {
        Route::prefix('iam')->group(function (): void {
            Route::get('permissions', [IamController::class, 'permissions'])
                ->middleware('permission:ad.iam.view_catalog');
            Route::get('permissions/{code}/holders', [IamController::class, 'holders'])
                ->middleware('permission:ad.iam.view_catalog')
                ->where('code', '[A-Za-z0-9._-]+');
            Route::get('roles', [IamController::class, 'roles'])
                ->middleware('permission:ad.iam.view_catalog');
            Route::post('roles/preview', [IamController::class, 'previewRole'])
                ->middleware('permission:ad.iam.view_catalog');
            Route::post('roles', [IamController::class, 'storeRole'])
                ->middleware('permission:ad.iam.role_create');
            Route::post('roles/{id}/approve', [IamController::class, 'approveRole'])
                ->middleware('permission:ad.iam.role_approve');
            // DOC-08 defines `ad.iam.role_update` for rewriting permissions of an
            // existing role (create ≠ update). Seeded BF-08 2026-09-24.
            Route::put('roles/{id}/permissions', [IamController::class, 'replacePermissions'])
                ->middleware('permission:ad.iam.role_update');
            Route::post('assignments', [IamController::class, 'assign'])
                ->middleware('permission:ad.iam.role_assign');
            Route::delete('assignments', [IamController::class, 'revoke'])
                ->middleware('permission:ad.iam.role_assign');
            Route::post('simulate', [IamController::class, 'simulate'])
                ->middleware('permission:ad.iam.simulate');
            Route::post('temp-grants', [IamController::class, 'requestTempGrant'])
                ->middleware('permission:ad.iam.grant_temp');
            Route::post('temp-grants/{id}/approve', [IamController::class, 'approveTempGrant'])
                ->middleware('permission:ad.iam.grant_temp');
            Route::get('sod-rules', [IamController::class, 'sodRules'])
                ->middleware('permission:ad.iam.sod_rules');
            Route::post('reviews', [IamController::class, 'startReview'])
                ->middleware('permission:ad.audit.review');
            Route::get('reviews/{id}', [IamController::class, 'showReview'])
                ->middleware('permission:ad.audit.review');
            Route::post('reviews/{id}/items/{itemId}/decide', [IamController::class, 'decideReviewItem'])
                ->middleware('permission:ad.audit.review');
            Route::get('approval-requests', [IamController::class, 'approvalInbox'])
                ->middleware('permission:ad.iam.view_catalog');
            Route::post('approval-requests/{id}/decide', [IamController::class, 'decideApproval'])
                ->middleware('permission:ad.iam.role_approve');
        });

        Route::get('audit', [AuditController::class, 'index'])
            ->middleware('permission:ad.audit.view');
        Route::post('audit/export', [AuditController::class, 'export'])
            ->middleware('permission:ad.audit.export');
    });
