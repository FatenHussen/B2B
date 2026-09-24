<?php

declare(strict_types=1);

/**
 * Rule 10, the other half: every place the channel scope is lifted is known, and says why.
 *
 * Two spellings lift the scope — `acrossChannels()` and the raw
 * `withoutGlobalScope('channel')` it wraps — and until BE-C12 only the first was counted:
 * CLAUDE.md said "two places", the truth was twenty-three of one spelling and fifteen of
 * the other that nobody counted. This test counts both, pins the number per file so a
 * new site cannot appear silently, and requires a written reason within reach of each —
 * an escape hatch without its reason beside it is the thing rule 10 forbids.
 *
 * The reason is a comment: `//` or a docblock line, on the same line or within the six
 * lines above the site. A relation method's docblock counts for the escape on its
 * `return`; a chain broken across lines counts from the line that starts it.
 */

use Illuminate\Support\Facades\File;

/** Sites per file, pinned. A change here is a change to rule 10's inventory — say why. */
const CHANNEL_SCOPE_ESCAPES = [
    'app-modules/catalog/src/Application/Queries/ListRepProducts.php' => 1,
    'app-modules/catalog/src/Application/Queries/ListRetailerCategories.php' => 4,
    'app-modules/catalog/src/Application/Queries/RetailerBrands.php' => 2,
    'app-modules/catalog/src/Application/Queries/RetailerHome.php' => 1,
    'app-modules/catalog/src/Application/Queries/ShowRepProduct.php' => 1,
    'app-modules/catalog/src/Application/Support/VisibleCatalogQuery.php' => 2,
    'app-modules/catalog/src/Domain/Models/Product.php' => 2,
    'app-modules/catalog/src/Infrastructure/EloquentCatalogProductLookup.php' => 4,
    'app-modules/catalog/src/Infrastructure/EloquentCatalogSyncSource.php' => 1,
    'app-modules/content/src/Application/Actions/RecordBannerClick.php' => 1,
    'app-modules/content/src/Application/Queries/ListHomeBlocks.php' => 2,
    'app-modules/finance/src/Application/Queries/ListRetailerDebts.php' => 2,
    'app-modules/finance/src/Application/Queries/ShowRetailerAccountStatement.php' => 3,
    'app-modules/finance/src/Application/Queries/ShowRetailerAccountSummary.php' => 3,
    'app-modules/loyalty/src/Application/Actions/RedeemLoyaltyReward.php' => 1,
    'app-modules/loyalty/src/Application/Queries/ShowAppLoyalty.php' => 1,
    'app-modules/ordering/src/Application/Actions/AcceptAssignment.php' => 1,
    'app-modules/ordering/src/Application/Actions/AddRepCartLine.php' => 1,
    'app-modules/ordering/src/Application/Actions/AddRetailerCartLine.php' => 1,
    'app-modules/ordering/src/Application/Actions/CancelSubOrder.php' => 1,
    'app-modules/ordering/src/Application/Actions/RejectAssignment.php' => 1,
    'app-modules/ordering/src/Application/Actions/ReorderSubOrder.php' => 2,
    'app-modules/ordering/src/Application/Actions/SubmitRepCartSection.php' => 1,
    'app-modules/ordering/src/Application/Actions/SubmitRetailerCart.php' => 1,
    'app-modules/ordering/src/Application/Actions/UpdateRetailerCartSection.php' => 1,
    'app-modules/ordering/src/Application/Queries/ListRepAssignments.php' => 1,
    'app-modules/ordering/src/Application/Queries/ListRepOrders.php' => 1,
    'app-modules/ordering/src/Application/Queries/ListRepScheduledOrders.php' => 1,
    'app-modules/ordering/src/Application/Queries/ListRetailerOrders.php' => 1,
    'app-modules/ordering/src/Application/Queries/ShowRetailerOrder.php' => 1,
    'app-modules/ordering/src/Application/Queries/TrackRetailerOrder.php' => 1,
    'app-modules/ordering/src/Domain/Models/Cart.php' => 1,
    'app-modules/ordering/src/Domain/Models/CartLine.php' => 1,
    'app-modules/ordering/src/Domain/Models/Order.php' => 2,
    'app-modules/ordering/src/Infrastructure/EloquentOpenOrderCounter.php' => 1,
    'app-modules/pricing/src/Application/Jobs/ApplyPriceListScheduleJob.php' => 1,
    'app-modules/pricing/src/Infrastructure/EloquentPricingEngine.php' => 1,
    'app-modules/pricing/src/Infrastructure/EloquentRepCommercialLimits.php' => 1,
    'app-modules/promotion/src/Infrastructure/EloquentOfferApplicator.php' => 1,
    'app-modules/promotion/src/Infrastructure/EloquentOfferConsumption.php' => 2,
    'app-modules/promotion/src/Infrastructure/EloquentOfferFeed.php' => 1,
    'app-modules/returns/src/Application/Listeners/ReverseOfferOnFullReturn.php' => 1,
    'app-modules/returns/src/Application/ReturnsWorkspace.php' => 1,
    'app-modules/tenancy/src/Infrastructure/EloquentChannelDirectory.php' => 2,
    'app-modules/tenancy/src/Infrastructure/EloquentWarehouseDirectory.php' => 4,
];

/**
 * Every escape site in $code: [line number (1-based), spelling, has a reason].
 *
 * @return list<array{0: int, 1: string, 2: bool}>
 */
function channelScopeEscapesIn(string $code): array
{
    $lines = explode("\n", $code);
    $sites = [];

    foreach ($lines as $i => $line) {
        // The trait's own definition of the scope, and the docs that name the token in
        // prose, are not sites.
        if (str_contains($line, 'function scopeAcrossChannels') || preg_match('/^\s*(\/\/|\*|\/\*\*)/', $line)) {
            continue;
        }

        $spelling = null;
        if (str_contains($line, 'acrossChannels(')) {
            $spelling = 'acrossChannels()';
        } elseif (preg_match('/withoutGlobalScope\(\s*[\'"]channel[\'"]\s*\)/', $line)) {
            $spelling = "withoutGlobalScope('channel')";
        }
        if ($spelling === null) {
            continue;
        }

        $reason = (bool) preg_match('/\/\/|\/\*/', $line);
        for ($k = max(0, $i - 6); $k < $i && ! $reason; $k++) {
            $reason = (bool) preg_match('/^\s*(\/\/|\*|\/\*\*)\s*\S/', $lines[$k]);
        }

        $sites[] = [$i + 1, $spelling, $reason];
    }

    return $sites;
}

/**
 * @return array{sites: array<string, int>, unreasoned: list<string>}
 */
function channelScopeEscapes(): array
{
    $sites = [];
    $unreasoned = [];
    $root = str_replace('\\', '/', base_path()).'/';

    foreach (File::allFiles(base_path('app-modules')) as $file) {
        $path = str_replace('\\', '/', $file->getPathname());
        if ($file->getExtension() !== 'php' || ! str_contains($path, '/src/')) {
            continue;
        }
        if (str_ends_with($path, '/Concerns/BelongsToChannel.php')) {
            continue;
        }

        $rel = substr($path, strlen($root));
        foreach (channelScopeEscapesIn((string) file_get_contents($path)) as [$line, $spelling, $reason]) {
            $sites[$rel] = ($sites[$rel] ?? 0) + 1;
            if (! $reason) {
                $unreasoned[] = "{$rel}:{$line} {$spelling}";
            }
        }
    }

    ksort($sites);

    return ['sites' => $sites, 'unreasoned' => $unreasoned];
}

it('lifts the channel scope only where the inventory says, both spellings counted', function () {
    // The diff is the work list: a file added here without a pin, or pinned and gone.
    expect(channelScopeEscapes()['sites'])->toBe(CHANNEL_SCOPE_ESCAPES);
})->group('arch');

it('writes a reason beside every escape', function () {
    expect(channelScopeEscapes()['unreasoned'])->toBe([]);
})->group('arch');

it('pins the inventory to the number rule 10 states', function () {
    // CLAUDE.md rule 10 names this number. When it moves, the rule's text moves with it.
    expect(array_sum(CHANNEL_SCOPE_ESCAPES))->toBe(67);
})->group('arch');

it('actually catches an escape written without a reason', function () {
    $bare = "<?php\nclass X {\n    public function go() {\n        return Product::withoutGlobalScope('channel')->get();\n    }\n}\n";
    $chained = "<?php\nclass X {\n    public function go() {\n        return SubOrder::query()\n            ->acrossChannels()\n            ->first();\n    }\n}\n";

    expect(channelScopeEscapesIn($bare))->toBe([[4, "withoutGlobalScope('channel')", false]])
        ->and(channelScopeEscapesIn($chained))->toBe([[5, 'acrossChannels()', false]]);
})->group('arch');

it('accepts a reason on the same line, above it, or in the method docblock', function () {
    $inline = "<?php\n\$q = SubOrder::query()->acrossChannels(); // the caller has no tenant\n";
    $above = "<?php\n// acrossChannels(), per rule 10: no tenant on /app/*, retailer_id below isolates.\n\$q = SubOrder::query()->acrossChannels();\n";
    $docblock = "<?php\nclass P {\n    /**\n     * Lifted: the child is on the parent's channel by construction.\n     */\n    public function brand(): BelongsTo\n    {\n        return \$this->belongsTo(Brand::class)->withoutGlobalScope('channel');\n    }\n}\n";
    $tooFar = "<?php\n// a reason\n\n\n\n\n\n\n\n\$q = SubOrder::query()->acrossChannels();\n";

    expect(channelScopeEscapesIn($inline)[0][2])->toBeTrue()
        ->and(channelScopeEscapesIn($above)[0][2])->toBeTrue()
        ->and(channelScopeEscapesIn($docblock)[0][2])->toBeTrue()
        ->and(channelScopeEscapesIn($tooFar)[0][2])->toBeFalse();
})->group('arch');
