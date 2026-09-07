<?php

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\Reference\Presentation\Http\Controllers\ChannelZoneController;
use Modules\Reference\Presentation\Http\Controllers\GovernorateController;
use Modules\Reference\Presentation\Http\Controllers\ZoneController;

/*
 * Shared reference data. No prefix here names a guard, so the four are listed
 * explicitly: every authenticated client may read governorates and zones, and the
 * `ad.refs.*` catalog codes decide who may write. This replaces `auth:sanctum`, which
 * named a guard config/auth.php never defines.
 *
 * Writing reference data is a platform permission. The gates below used to name
 * `can:settings.*`, an interim AccessMatrix vocabulary that was seeded on all four
 * guards at once, so a channel manager's own guard satisfied a gate written for the
 * platform — a channel user could create a governorate. `ad.refs.*` exists on the
 * platform guard only, and Spatie answers a permission absent from the caller's guard
 * with false rather than an exception, so the other three guards now get 403.
 *
 * The public snapshot EP-PB-001 (`GET /api/v1/public/refs`) is deliberately absent: it
 * is a contract path and belongs to the separate route-migration ticket.
 */
Route::middleware(['api', 'auth:platform,channel,warehouse,app', 'tenant', SubstituteBindings::class])
    ->prefix('api/v1')
    ->group(function () {
        Route::get('governorates', [GovernorateController::class, 'index']);
        Route::get('governorates/{governorate}', [GovernorateController::class, 'show']);
        Route::post('governorates', [GovernorateController::class, 'store'])->middleware('can:ad.refs.create');
        Route::put('governorates/{governorate}', [GovernorateController::class, 'update'])->middleware('can:ad.refs.update');
        // EP-AD-043A, and the resolution of the mismatch this line used to carry: the
        // gate said `ad.refs.disable` while the action hard deleted. There is no DELETE
        // any more. Rule 12 and the catalog agree — "المحافظات لا تُحذف، تُعطَّل فقط" —
        // and DOC-08 defines no `ad.refs.delete` to gate one with.
        Route::patch('governorates/{governorate}/status', [GovernorateController::class, 'changeStatus'])->middleware('can:ad.refs.disable');

        Route::get('zones', [ZoneController::class, 'index']);
        Route::get('zones/{zone}', [ZoneController::class, 'show']);
        Route::post('zones', [ZoneController::class, 'store'])->middleware('can:ad.refs.create');
        Route::put('zones/{zone}', [ZoneController::class, 'update'])->middleware('can:ad.refs.update');
        // EP-AD-034 — and note the number: the zone status route sits outside the 043
        // family that governorates and activity types use. BE-R03 calls that a documented
        // contract exception, and a test pins it so it cannot quietly drift to 043.
        // DELETE is withdrawn, as for governorates: rule 12, and no `ad.refs.delete`.
        Route::patch('zones/{zone}/status', [ZoneController::class, 'changeStatus'])->middleware('can:ad.refs.disable');
    });

/*
 * Channel coverage rows. The prefix names the guard, and DOC-08 gives the channel two
 * codes for its zones — `view` and `manage` — so creating and deleting a coverage row
 * share one gate. That is coarser than the three `settings.*` gates it replaces, and it
 * is what the document says.
 */
Route::middleware(['api', 'auth:channel', 'tenant', SubstituteBindings::class])
    ->prefix('api/v1/channel')
    ->group(function () {
        Route::middleware('can:sc.zones.view')->group(function () {
            Route::get('zones', [ChannelZoneController::class, 'index']);
            Route::post('zones', [ChannelZoneController::class, 'store'])->middleware('can:sc.zones.manage');
            Route::delete('zones/{channelZone}', [ChannelZoneController::class, 'destroy'])->middleware('can:sc.zones.manage');
        });
    });
