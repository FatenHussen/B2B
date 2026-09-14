<?php

declare(strict_types=1);

/**
 * Rule 8, for the channel: `status` changes only through the lifecycle service.
 *
 * `$guarded` closes mass assignment, and nothing else. A direct `$channel->status = …`
 * anywhere in the module walks straight past it, which is how SubOrder ended up with
 * nine writers around one state machine that only ever checked (BE-O15). This test is
 * the other half of the guard: it reads the Tenancy source and names every file that
 * assigns a status, and the list it accepts has one entry.
 */

use Illuminate\Support\Facades\File;

/** Files permitted to assign a status inside Tenancy — the lifecycle, and nothing else. */
const CHANNEL_STATUS_WRITERS = [
    'app-modules/tenancy/src/Application/Services/ChannelLifecycle.php',
];

/**
 * Whether $code assigns a status: `->status =` (not `==`/`===`) or a `forceFill(`, which
 * exists precisely to write past `$guarded`.
 */
function writesStatus(string $code): bool
{
    return preg_match('/->status\s*=(?!=)/', $code) === 1
        || preg_match('/\bforceFill\s*\(/', $code) === 1;
}

/**
 * @return list<string>
 */
function channelStatusWriters(): array
{
    $root = base_path('app-modules/tenancy/src');
    $found = [];

    foreach (File::allFiles($root) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        if (writesStatus((string) file_get_contents($file->getPathname()))) {
            $found[] = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));
        }
    }

    sort($found);

    return $found;
}

it('lets only the lifecycle service assign a channel status', function () {
    expect(channelStatusWriters())->toBe(CHANNEL_STATUS_WRITERS);
})->group('arch');

it('actually catches a direct status assignment', function () {
    // The detector proved rather than trusted.
    expect(writesStatus('$channel->status = ChannelStatus::Archived;'))->toBeTrue()
        ->and(writesStatus('$channel->forceFill([\'status\' => \'archived\'])->save();'))->toBeTrue()
        ->and(writesStatus('if ($channel->status === ChannelStatus::Active) {'))->toBeFalse()
        ->and(writesStatus('->where(\'status\', ChannelStatus::Active)'))->toBeFalse()
        ->and(writesStatus("'status' => \$this->status->value,"))->toBeFalse();
})->group('arch');
