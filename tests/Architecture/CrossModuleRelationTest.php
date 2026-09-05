<?php

declare(strict_types=1);

/**
 * BE-F04 requirement 4 — the rule that was missing.
 *
 * Cross-boundary references are identifiers. `$line->product_id`, never
 * `$line->product()`. An Eloquent relation pointing at another module's model is a join
 * across a boundary: it reintroduces the shared schema the modules exist to avoid, and
 * it survives requirement 1 whenever the target is written as a fully qualified name
 * instead of an import.
 *
 * `\x5c` inside these patterns is a single backslash. Written that way the namespace
 * separators stay readable instead of turning into a wall of escapes.
 */

use Symfony\Component\Finder\Finder;
use Tests\Support\ModuleNames;

const RELATION_METHODS = [
    'belongsTo', 'belongsToMany', 'hasMany', 'hasManyThrough', 'hasOne',
    'hasOneThrough', 'hasOneOfMany', 'morphMany', 'morphOne', 'morphTo',
    'morphToMany', 'morphedByMany',
];

/**
 * Every Eloquent relation declared in $code whose target class belongs to another module.
 *
 * @return list<string>
 */
function crossModuleRelations(string $code): array
{
    $namespace = ModuleNames::namespaceOf($code);

    if ($namespace === null) {
        return [];
    }

    $owner = ModuleNames::owning($namespace.ModuleNames::SEPARATOR);

    if ($owner === null) {
        return [];
    }

    $aliases = ModuleNames::imports($code);
    $methods = implode('|', RELATION_METHODS);
    $found = [];

    // ->belongsTo(Product::class) and ->belongsTo(\Modules\Catalog\...\Product::class)
    preg_match_all(
        '/->(?:'.$methods.')\s*\(\s*(\x5c?)([A-Za-z_][\w\x5c]*)::class/',
        $code,
        $calls,
        PREG_SET_ORDER,
    );

    foreach ($calls as $call) {
        $found[] = ModuleNames::resolve($call[2], $call[1] !== '', $namespace, $aliases);
    }

    // ->belongsTo('Modules\Catalog\Domain\Models\Product')
    preg_match_all(
        '/->(?:'.$methods.')\s*\(\s*[\'"]\x5c?(Modules\x5c[\w\x5c]*)[\'"]/',
        $code,
        $strings,
        PREG_SET_ORDER,
    );

    foreach ($strings as $string) {
        $found[] = $string[1];
    }

    $foreign = array_filter(
        $found,
        fn (string $class): bool => ModuleNames::owning($class) !== null && ModuleNames::owning($class) !== $owner,
    );

    return array_values(array_unique($foreign));
}
it('declares no Eloquent relation across a module boundary', function () {
    $offenders = [];

    $sources = Finder::create()->files()->name('*.php')->in(glob(base_path('app-modules/*/src')));

    foreach ($sources as $source) {
        $relations = crossModuleRelations($source->getContents());

        if ($relations !== []) {
            $file = str_replace(base_path().DIRECTORY_SEPARATOR, '', $source->getPathname());
            $offenders[$file] = $relations;
        }
    }

    expect($offenders)->toBe([]);
})->group('arch');

it('actually catches a relation across a module boundary', function () {
    $violating = <<<'PHP'
    <?php
    namespace Modules\Ordering\Domain\Models;

    use Illuminate\Database\Eloquent\Model;
    use Modules\Catalog\Domain\Models\Product;

    class SubOrderLine extends Model
    {
        public function product() { return $this->belongsTo(Product::class); }
        public function brand() { return $this->belongsTo(\Modules\Catalog\Domain\Models\Brand::class); }
        public function tags() { return $this->morphToMany('Modules\Content\Domain\Models\Tag'); }
    }
    PHP;

    expect(crossModuleRelations($violating))->toBe([
        'Modules\Catalog\Domain\Models\Product',
        'Modules\Catalog\Domain\Models\Brand',
        'Modules\Content\Domain\Models\Tag',
    ]);
})->group('arch');

it('leaves a relation inside its own module alone', function () {
    $compliant = <<<'PHP'
    <?php
    namespace Modules\Ordering\Domain\Models;

    use Illuminate\Database\Eloquent\Model;

    class SubOrder extends Model
    {
        public function lines() { return $this->hasMany(SubOrderLine::class); }
        public function retailerId(): int { return $this->retailer_id; }
    }
    PHP;

    expect(crossModuleRelations($compliant))->toBe([]);
})->group('arch');
