<?php

declare(strict_types=1);

/**
 * BE-F04 requirement 1, reported by file.
 *
 * This replaces the 462 generated `expect('Modules\X')->not->toUse('Modules\Y\Domain\Models')`
 * pair rules that used to live in ArchitectureTest.php. They caught the same breaches, but
 * `Pest\Arch\Blueprint::expectToUse()` hands its failure callback the two namespace strings
 * and discards the Violation object carrying the path — so the build could say *which pair*
 * broke and never *which file*. With one offender that costs a grep. With two on the same
 * day it costs the afternoon.
 *
 * One rule, one list, every offending path named.
 */

use Symfony\Component\Finder\Finder;
use Tests\Support\ModuleNames;

/**
 * Every `Modules\Other\Domain\Models\Something` a module's own source refers to.
 *
 * Catches all three ways to name one: an import, a fully qualified reference inline, and a
 * class name written as a string. Comments are stripped first — a docblock is not a
 * dependency.
 *
 * @return list<string>
 */
function foreignModelReferences(string $code): array
{
    $namespace = ModuleNames::namespaceOf($code);

    if ($namespace === null) {
        return [];
    }

    $owner = ModuleNames::owning($namespace.ModuleNames::SEPARATOR);

    if ($owner === null) {
        return [];
    }

    preg_match_all(
        '/Modules\x5c{1,2}([A-Za-z0-9_]+)\x5c{1,2}Domain\x5c{1,2}Models\x5c{1,2}([A-Za-z0-9_]+)/',
        ModuleNames::withoutComments($code),
        $matches,
        PREG_SET_ORDER,
    );

    $foreign = [];

    foreach ($matches as $match) {
        if ($match[1] === $owner) {
            continue;
        }

        $foreign[] = 'Modules'.ModuleNames::SEPARATOR.$match[1]
            .ModuleNames::SEPARATOR.'Domain'.ModuleNames::SEPARATOR.'Models'
            .ModuleNames::SEPARATOR.$match[2];
    }

    return array_values(array_unique($foreign));
}

/**
 * @return list<string>
 */
function crossModuleModelImports(): array
{
    $offenders = [];

    $sources = Finder::create()
        ->files()
        ->name('*.php')
        ->exclude('vendor')
        ->in(glob(base_path('app-modules/*')));

    foreach ($sources as $source) {
        $foreign = foreignModelReferences($source->getContents());

        if ($foreign === []) {
            continue;
        }

        $path = str_replace([base_path().DIRECTORY_SEPARATOR, '\\'], ['', '/'], $source->getPathname());

        foreach ($foreign as $class) {
            $offenders[] = $path.'  ->  '.$class;
        }
    }

    sort($offenders);

    return $offenders;
}

it('imports no other module Eloquent model', function () {
    expect(crossModuleModelImports())->toBe([]);
})->group('arch');

it('actually catches an import of another module model', function () {
    $violating = <<<'PHP'
    <?php
    namespace Modules\Ordering\Application\Actions;

    use Modules\Catalog\Domain\Models\Product;

    final class SubmitOrder
    {
        public function run(): void
        {
            Product::query()->first();
            \Modules\Pricing\Domain\Models\PriceList::query()->first();
            $class = 'Modules\Inventory\Domain\Models\StockItem';
        }
    }
    PHP;

    expect(foreignModelReferences($violating))->toBe([
        'Modules\Catalog\Domain\Models\Product',
        'Modules\Pricing\Domain\Models\PriceList',
        'Modules\Inventory\Domain\Models\StockItem',
    ]);
})->group('arch');

it('leaves a module own models and a contract alone', function () {
    $compliant = <<<'PHP'
    <?php
    namespace Modules\Ordering\Application\Actions;

    use Modules\Core\Contracts\RetailerDirectory;
    use Modules\Ordering\Domain\Models\SubOrder;

    final class SubmitOrder
    {
        public function __construct(private readonly RetailerDirectory $retailers) {}

        public function run(int $retailerId): void
        {
            $this->retailers->find($retailerId);
            SubOrder::query()->first();
        }
    }
    PHP;

    expect(foreignModelReferences($compliant))->toBe([]);
})->group('arch');

it('does not count a docblock mention as an import', function () {
    $annotated = <<<'PHP'
    <?php
    namespace Modules\Ordering\Application\Actions;

    final class SubmitOrder
    {
        /**
         * Replaces the old Modules\Catalog\Domain\Models\Product lookup.
         */
        public function run(): void {}
    }
    PHP;

    expect(foreignModelReferences($annotated))->toBe([]);
})->group('arch');
