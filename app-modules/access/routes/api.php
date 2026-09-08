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
            // `ad.iam.role_create` is wider than what this route does. DOC-08 defines it
            // as "إنشاء دور مخصص" — creating a custom role — while this rewrites the
            // permissions of a role that already exists and is possibly already approved.
            // Holding it therefore grants two distinct powers under one name.
            //
            // There is no better code: `ad.iam.*` has exactly seven members —
            // role_create, role_approve, role_assign, grant_temp, sod_rules,
            // view_catalog, simulate — and none describes editing an existing role.
            //
            // That is a gap in DOC-08 rather than a choice, and the document says so
            // itself: `ad.refs` and `ad.team` both separate creation from modification
            // (refs.create/refs.update, team.invite/team.update). `ad.iam` alone does
            // not. DOC-08 needs `ad.iam.role_update`; until it has one this gate is the
            // closest true code, and picking anything else would be inventing a name.
            // Recorded in docs/api/permission-gate-audit.md.
            Route::put('roles/{id}/permissions', [IamController::class, 'replacePermissions'])
                ->middleware('permission:ad.iam.role_create');
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
