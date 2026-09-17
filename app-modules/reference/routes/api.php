<?php

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\Reference\Presentation\Http\Controllers\ActivityTypeController;
use Modules\Reference\Presentation\Http\Controllers\ChannelZoneController;
use Modules\Reference\Presentation\Http\Controllers\CurrencyController;
use Modules\Reference\Presentation\Http\Controllers\EquipmentController;
use Modules\Reference\Presentation\Http\Controllers\FxRateController;
use Modules\Reference\Presentation\Http\Controllers\GovernorateController;
use Modules\Reference\Presentation\Http\Controllers\PublicRefsController;
use Modules\Reference\Presentation\Http\Controllers\ReferenceImportController;
use Modules\Reference\Presentation\Http\Controllers\RootCategoryController;
use Modules\Reference\Presentation\Http\Controllers\SaleUnitController;
use Modules\Reference\Presentation\Http\Controllers\ZoneController;

/*
 * EP-PB-001 — the registration snapshot. No guard, no Bearer, no idempotency key (a
 * GET). Channels are never in this payload (REQ-IN-06); BE-R10 pins that with a test.
 */
Route::middleware(['api', SubstituteBindings::class])
    ->prefix('api/v1/public')
    ->group(function (): void {
        Route::get('refs', [PublicRefsController::class, 'index']);
    });

/*
 * The platform back office: every reference write, and the platform's own reads, under
 * the catalog's `/platform/refs/*` paths. The prefix names the guard (GuardTest), and the
 * `ad.refs.*` codes from DOC-08 decide who may do what — `ad.refs.view` reads,
 * `create` / `update` / `disable` write, `currency` owns currencies and FX rates alone
 * (BE-R08 requirement 2), `import` owns the bulk upload.
 *
 * There is no DELETE anywhere below. Rule 12: a reference entity is disabled, never
 * removed, and DOC-08 defines no `ad.refs.delete` to gate one with. EP-AD-034 — the zone
 * status route — sits outside the 043 family on purpose; ZoneTest pins it.
 */
Route::middleware(['api', 'auth:platform', 'guard.tokenable:platform', 'tenant', SubstituteBindings::class])
    ->prefix('api/v1/platform/refs')
    ->group(function (): void {
        Route::middleware('permission:ad.refs.view')->group(function (): void {
            Route::get('governorates', [GovernorateController::class, 'index']);
            Route::get('governorates/{governorate}', [GovernorateController::class, 'show']);
            Route::get('zones', [ZoneController::class, 'index']);
            Route::get('zones/{zone}', [ZoneController::class, 'show']);
            Route::get('activity-types', [ActivityTypeController::class, 'index']);
            Route::get('activity-types/{activityType}', [ActivityTypeController::class, 'show']);
            Route::get('root-categories', [RootCategoryController::class, 'index']);
            Route::get('root-categories/{rootCategory}', [RootCategoryController::class, 'show']);
            Route::get('sale-units', [SaleUnitController::class, 'index']);
            Route::get('sale-units/{saleUnit}', [SaleUnitController::class, 'show']);
            Route::get('equipments', [EquipmentController::class, 'index']);
            Route::get('equipments/{equipment}', [EquipmentController::class, 'show']);
        });

        Route::middleware('permission:ad.refs.create')->group(function (): void {
            Route::post('governorates', [GovernorateController::class, 'store']);   // EP-AD-031
            Route::post('zones', [ZoneController::class, 'store']);                 // EP-AD-033
            Route::post('activity-types', [ActivityTypeController::class, 'store']); // EP-AD-035B
            Route::post('root-categories', [RootCategoryController::class, 'store']); // EP-AD-036B
            Route::post('sale-units', [SaleUnitController::class, 'store']);        // EP-AD-037B
            Route::post('equipments', [EquipmentController::class, 'store']);       // EP-AD-038B
        });

        Route::middleware('permission:ad.refs.update')->group(function (): void {
            Route::put('governorates/{governorate}', [GovernorateController::class, 'update']);      // EP-AD-042A
            Route::put('zones/{zone}', [ZoneController::class, 'update']);                           // EP-AD-042B
            Route::put('activity-types/{activityType}', [ActivityTypeController::class, 'update']);  // EP-AD-042C
            Route::put('root-categories/{rootCategory}', [RootCategoryController::class, 'update']); // EP-AD-042D
            Route::put('sale-units/{saleUnit}', [SaleUnitController::class, 'update']);              // EP-AD-042E
            Route::put('equipments/{equipment}', [EquipmentController::class, 'update']);            // EP-AD-042F
        });

        Route::middleware('permission:ad.refs.disable')->group(function (): void {
            Route::patch('governorates/{governorate}/status', [GovernorateController::class, 'changeStatus']);      // EP-AD-043A
            Route::patch('zones/{zone}/status', [ZoneController::class, 'changeStatus']);                           // EP-AD-034
            Route::patch('activity-types/{activityType}/status', [ActivityTypeController::class, 'changeStatus']);  // EP-AD-043B
            Route::patch('root-categories/{rootCategory}/status', [RootCategoryController::class, 'changeStatus']); // EP-AD-043C
            Route::patch('sale-units/{saleUnit}/status', [SaleUnitController::class, 'changeStatus']);              // EP-AD-043D
            Route::patch('equipments/{equipment}/status', [EquipmentController::class, 'changeStatus']);            // EP-AD-043E
        });

        Route::middleware('permission:ad.refs.currency')->group(function (): void {
            Route::get('currencies', [CurrencyController::class, 'index']);                       // EP-AD-039A
            Route::get('currencies/{currency}', [CurrencyController::class, 'show']);
            Route::post('currencies', [CurrencyController::class, 'store']);                      // EP-AD-039B
            Route::put('currencies/{currency}', [CurrencyController::class, 'update']);           // EP-AD-042G
            Route::patch('currencies/{currency}/status', [CurrencyController::class, 'changeStatus']); // EP-AD-043F
            Route::get('fx-rates', [FxRateController::class, 'index']);                           // EP-AD-043G
            Route::post('fx-rates', [FxRateController::class, 'store']);                          // EP-AD-040
        });

        Route::post('import', [ReferenceImportController::class, 'store'])->middleware('permission:ad.refs.import'); // EP-AD-041
    });

/*
 * Shared read-only reference data for every signed-in client. No prefix here names a
 * guard, so the four are listed explicitly. The channel dashboard pack reads these three
 * paths today, which is why they stay; every write moved to `/platform/refs` above and
 * nothing under here mutates anything.
 */
Route::middleware(['api', 'auth:platform,channel,warehouse,app', 'tenant', SubstituteBindings::class])
    ->prefix('api/v1')
    ->group(function (): void {
        Route::get('governorates', [GovernorateController::class, 'index']);
        Route::get('governorates/{governorate}', [GovernorateController::class, 'show']);
        Route::get('zones', [ZoneController::class, 'index']);
        Route::get('zones/{zone}', [ZoneController::class, 'show']);
        Route::get('currencies', [CurrencyController::class, 'index']);
        Route::get('currencies/{currency}', [CurrencyController::class, 'show']);
    });

/*
 * Channel coverage rows. The prefix names the guard, and DOC-08 gives the channel two
 * codes for its zones — `view` and `manage` — so creating and deleting a coverage row
 * share one gate. That is coarser than the three `settings.*` gates it replaces, and it
 * is what the document says.
 */
Route::middleware(['api', 'auth:channel', 'tenant', SubstituteBindings::class])
    ->prefix('api/v1/channel')
    ->group(function (): void {
        Route::middleware('can:sc.zones.view')->group(function (): void {
            Route::get('zones', [ChannelZoneController::class, 'index']);
            Route::post('zones', [ChannelZoneController::class, 'store'])->middleware('can:sc.zones.manage');
            Route::delete('zones/{channelZone}', [ChannelZoneController::class, 'destroy'])->middleware('can:sc.zones.manage');
        });
    });
