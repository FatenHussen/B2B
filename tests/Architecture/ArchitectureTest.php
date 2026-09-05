<?php

/**
 * Architecture rules from CLAUDE.md and DOC-10 sections 2.2 and 5.
 * These fail the build. They are why the boundaries are real and not aspirational.
 */
// Rule 1 — no module imports another module's Eloquent model — used to be 462 generated
// pair rules here. They are gone: `not->toUse` names the pair and never the file, because
// Pest\Arch\Blueprint::expectToUse() discards the Violation carrying the path. The rule now
// lives in CrossModuleModelImportTest.php, scans the source and reports every offending
// path in one list.

arch('no float on any money path')
    ->expect(['Modules\\Finance', 'Modules\\Pricing', 'Modules\\Promotion'])
    ->not->toUse(['float', 'double'])
    ->group('arch');

arch('this is an API: no view layer outside PDF document templates')
    ->expect('Modules')
    ->not->toUse([
        'Illuminate\\View\\View',
        'Illuminate\\Support\\Facades\\View',
        'Illuminate\\Contracts\\View\\Factory',
    ])
    ->ignoring('Modules\\*\\Presentation\\Pdf')
    ->group('arch');

arch('controllers never redirect')
    ->expect('Modules')
    ->not->toUse(['Illuminate\\Http\\RedirectResponse', 'redirect'])
    ->group('arch');

// The two `toUseMiddleware` rules that used to sit here are gone: Pest has no such
// expectation, and a namespace cannot tell you what middleware a route was
// registered with. Guards are asserted against the real route table in GuardTest.php.

arch('app stays thin')
    ->expect('App')
    ->not->toUse('Illuminate\\Database\\Eloquent\\Model')
    ->group('arch');

arch('domain models stay inside their module')
    ->expect('Modules\\*\\Domain\\Models')
    ->toOnlyBeUsedIn(['Modules\\*\\Domain', 'Modules\\*\\Application', 'Modules\\*\\Infrastructure'])
    ->group('arch');
