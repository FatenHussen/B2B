<?php

declare(strict_types=1);

/**
 * The phpstan baseline is a counted debt, and this is the counter (BE-F11).
 *
 * On 2026-09-16 the analyser gate passed for the first time — by baselining 585 findings
 * that configuration alone could not remove: 213 relation declarations without their
 * related type and the 173 bare-Model symptoms they cause (counted together on purpose,
 * with no identifier ignored, so a fixed declaration takes its symptoms down with it),
 * arrays without a value type, unresolved templates, a few Pest residues. A baseline
 * with no counter is a grave: findings get added to it and nobody notices. This pins the
 * sum of every `count:` in the file, exactly, the way ChannelScopeEscapeTest pins the
 * escape inventory. It rises — the build fails. It falls, in a pay-down commit — the pin
 * is lowered in the same commit, so the number is always known, never discovered.
 *
 * `<=` was considered and rejected: a ceiling that stays high while the file shrinks
 * leaves silent headroom, which is the grave again.
 */

use Illuminate\Support\Facades\File;

/** The baseline's sum, pinned. Lower it in the commit that pays findings down. */
const PHPSTAN_BASELINE_FINDINGS = 474;

/**
 * @return array{sum: int, entries: int}
 */
function phpstanBaselineTotals(string $neon): array
{
    preg_match_all('/^\s*count:\s*(\d+)\s*$/m', $neon, $m);

    return ['sum' => (int) array_sum($m[1]), 'entries' => count($m[1])];
}

it('keeps the phpstan baseline at the pinned number of findings, falling only', function () {
    $file = base_path('phpstan-baseline.neon');

    expect(File::exists($file))->toBeTrue('phpstan-baseline.neon is gone; the gate would be lying');

    $totals = phpstanBaselineTotals((string) file_get_contents($file));

    // Exactly: a rise fails here; a fall asks for the pin to be lowered here, out loud.
    expect($totals['sum'])->toBe(PHPSTAN_BASELINE_FINDINGS);
})->group('arch');

it('keeps the baseline wired into phpstan.neon', function () {
    // The counter counts a file the analyser reads. Dropping the include would make the
    // gate red again without this test noticing — so it notices.
    $config = (string) file_get_contents(base_path('phpstan.neon'));

    expect(preg_match('/^\s*-\s*phpstan-baseline\.neon\s*$/m', $config))->toBe(1);
})->group('arch');

it('actually counts what a baseline entry says', function () {
    $sample = "parameters:\n\tignoreErrors:\n\t\t-\n\t\t\tmessage: '#x#'\n\t\t\tcount: 3\n\t\t\tpath: a.php\n\t\t-\n\t\t\tmessage: '#y#'\n\t\t\tcount: 1\n\t\t\tpath: b.php\n";

    expect(phpstanBaselineTotals($sample))->toBe(['sum' => 4, 'entries' => 2])
        ->and(phpstanBaselineTotals("parameters:\n\tignoreErrors: []\n"))->toBe(['sum' => 0, 'entries' => 0]);
})->group('arch');
