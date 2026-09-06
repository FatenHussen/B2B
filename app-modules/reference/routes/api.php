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
        // The gate says disable, the action deletes. DOC-08 defines no `ad.refs.delete`
        // because CLAUDE.md rule 12 says a reference entity is never hard deleted, and
        // `GovernorateController::destroy` hard deletes anyway. `ad.refs.disable` is the
        // nearest true code and closes the guard hole today; reconciling the verb with
        // the rule needs a `status` column this table does not have. Settled in BE-R02.
        Route::delete('governorates/{governorate}', [GovernorateController::class, 'destroy'])->middleware('can:ad.refs.disable');

        Route::get('zones', [ZoneController::class, 'index']);
        Route::get('zones/{zone}', [ZoneController::class, 'show']);
        Route::post('zones', [ZoneController::class, 'store'])->middleware('can:ad.refs.create');
        Route::put('zones/{zone}', [ZoneController::class, 'update'])->middleware('can:ad.refs.update');
        // Same mismatch as governorates, one step closer to resolvable: `zones` already
        // carries a `status` column cast to ZoneStatus, so this route could become a
        // real disable without a migration. It is still a hard delete today. BE-R03.
        Route::delete('zones/{zone}', [ZoneController::class, 'destroy'])->middleware('can:ad.refs.disable');
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
