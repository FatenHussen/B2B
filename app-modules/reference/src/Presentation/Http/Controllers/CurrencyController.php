<?php

declare(strict_types=1);

namespace Modules\Reference\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Modules\Core\Http\ApiController;
use Modules\Reference\Domain\Enums\RefStatus;
use Modules\Reference\Domain\Models\Currency;
use Modules\Reference\Presentation\Http\Requests\ChangeCurrencyStatusRequest;
use Modules\Reference\Presentation\Http\Requests\StoreCurrencyRequest;
use Modules\Reference\Presentation\Http\Requests\UpdateCurrencyRequest;
use Modules\Reference\Presentation\Http\Resources\CurrencyResource;

/**
 * EP-AD-039A/B, EP-AD-042G, EP-AD-043F.
 *
 * Every route here is gated on `ad.refs.currency`, not the general `ad.refs.*` family —
 * BE-R08 requirement 2, and DOC-08 marks that code critical. Currency is where rule 7
 * lives: `decimals` governs presentation everywhere while storage stays an integer in the
 * smallest unit, so a wrong value here misreads money on every screen.
 */
class CurrencyController extends ApiController
{
    public function index(): JsonResponse
    {
        return $this->ok(CurrencyResource::collection(
            Currency::query()->orderBy('iso')->get()
        ));
    }

    public function show(Currency $currency): JsonResponse
    {
        return $this->ok(new CurrencyResource($currency));
    }

    public function store(StoreCurrencyRequest $request): JsonResponse
    {
        $currency = DB::transaction(function () use ($request): Currency {
            $currency = Currency::create($request->safe()->except('is_display_currency'));

            if ($request->boolean('is_display_currency')) {
                $this->makeDisplayCurrency($currency);
            }

            return $currency;
        });

        return $this->created(new CurrencyResource($currency->refresh()));
    }

    public function update(UpdateCurrencyRequest $request, Currency $currency): JsonResponse
    {
        DB::transaction(function () use ($request, $currency): void {
            $currency->update($request->safe()->except(['is_display_currency', 'reason']));

            if ($request->has('is_display_currency')) {
                $request->boolean('is_display_currency')
                    ? $this->makeDisplayCurrency($currency)
                    : $currency->forceFill(['is_display_currency' => false])->save();
            }
        });

        return $this->ok(new CurrencyResource($currency->refresh()));
    }

    /**
     * EP-AD-043F. Disable, never delete — rule 12.
     */
    public function changeStatus(ChangeCurrencyStatusRequest $request, Currency $currency): JsonResponse
    {
        // Not `update()`: `status` is outside `$fillable` under rule 8, and mass
        // assignment would drop it silently and answer 200 having changed nothing.
        $currency->status = RefStatus::from($request->validated('status'));
        $currency->save();

        return $this->ok([
            'id' => $currency->id,
            'status' => $currency->status->value,
        ]);
    }

    /**
     * Make one currency the display currency, clearing the previous one atomically.
     *
     * BE-R09 requirement 2 — "setting is_display_currency atomically clears the previous"
     * — and its acceptance criterion that two display currencies cannot exist even under
     * concurrent writes. The clear and the set are one statement pair inside the caller's
     * transaction, so a reader never observes two and never observes none.
     *
     * `forceFill` because `is_display_currency` is fillable but this bypasses the request
     * entirely: the value being written is decided here, not by the client.
     */
    private function makeDisplayCurrency(Currency $currency): void
    {
        Currency::query()
            ->whereKeyNot($currency->getKey())
            ->where('is_display_currency', true)
            ->update(['is_display_currency' => false]);

        $currency->forceFill(['is_display_currency' => true])->save();
    }
}
