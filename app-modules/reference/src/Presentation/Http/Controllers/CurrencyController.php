<?php

declare(strict_types=1);

namespace Modules\Reference\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Core\Http\ApiController;
use Modules\Reference\Application\Services\ReferenceMutations;
use Modules\Reference\Domain\Enums\ReferenceEntity;
use Modules\Reference\Domain\Enums\RefStatus;
use Modules\Reference\Domain\Models\Currency;
use Modules\Reference\Presentation\Http\Requests\ChangeCurrencyStatusRequest;
use Modules\Reference\Presentation\Http\Requests\StoreCurrencyRequest;
use Modules\Reference\Presentation\Http\Requests\UpdateCurrencyRequest;
use Modules\Reference\Presentation\Http\Resources\CurrencyResource;

/**
 * BE-R08 — EP-AD-039A, 039B, 042G, 043F. Also the shared `GET /currencies` reads: every
 * client needs `decimals` to render money.
 *
 * Writes take `ad.refs.currency`, not the general `ad.refs.*` family (requirement 2).
 * `is_base` is never writable through the API — see BE-R09's decision: it is the unit
 * every stored amount is denominated in, and renaming it into `is_display_currency` would
 * hand an endpoint the power to reinterpret the whole ledger.
 */
class CurrencyController extends ApiController
{
    public function __construct(private readonly ReferenceMutations $mutations) {}

    public function index(Request $request): JsonResponse
    {
        $rows = Currency::query()
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request): void {
                $term = '%'.$request->string('search')->toString().'%';
                $q->where('iso', 'like', $term)->orWhere('name', 'like', $term);
            }))
            ->orderBy('iso')
            ->paginate(min((int) $request->integer('per_page', 25), 100));

        return $this->paginated($rows, fn (Currency $row) => (new CurrencyResource($row))->resolve());
    }

    public function show(Currency $currency): JsonResponse
    {
        return $this->ok(new CurrencyResource($currency));
    }

    public function store(StoreCurrencyRequest $request): JsonResponse
    {
        $currency = DB::transaction(function () use ($request): Currency {
            /** @var Currency $currency */
            $currency = $this->mutations->create(
                ReferenceEntity::Currencies,
                $request->safe()->except(['is_display_currency', 'reason']),
                $request->user(),
                $request->validated('reason'),
            );

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
            $this->mutations->update(
                ReferenceEntity::Currencies,
                $currency,
                $request->safe()->except(['is_display_currency', 'reason']),
                (string) $request->validated('reason'),
                $request->user(),
            );

            if ($request->has('is_display_currency')) {
                $request->boolean('is_display_currency')
                    ? $this->makeDisplayCurrency($currency)
                    : $currency->forceFill(['is_display_currency' => false])->save();
            }
        });

        return $this->ok(new CurrencyResource($currency->refresh()));
    }

    /** EP-AD-043F. Disable, not delete — rule 12, and DOC-08 defines no `ad.refs.delete`. */
    public function changeStatus(ChangeCurrencyStatusRequest $request, Currency $currency): JsonResponse
    {
        $to = RefStatus::from($request->validated('status'));

        $result = $this->mutations->changeStatus(
            ReferenceEntity::Currencies,
            $currency,
            $currency->status->value,
            $to->value,
            function () use ($currency, $to): void {
                // Not `update()`: `status` is outside `$fillable` under rule 8.
                $currency->status = $to;
            },
            (string) $request->validated('reason'),
            $request->user(),
        );

        return $this->ok(['id' => $result['id'], 'status' => $result['status']]);
    }

    /**
     * BE-R09 requirement 2: setting the display currency atomically clears the previous
     * one. Runs inside the caller's transaction, so two concurrent switches cannot leave
     * two rows flagged.
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
