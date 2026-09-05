<?php

/**
 * Architecture rules from CLAUDE.md and DOC-10 sections 2.2 and 5.
 * These fail the build. They are why the boundaries are real and not aspirational.
 */

$modules = [
    'Core', 'Identity', 'Access', 'Reference', 'Tenancy', 'Integration',
    'Catalog', 'Pricing', 'Promotion', 'Inventory', 'Loyalty', 'Content',
    'Notification', 'Support', 'PlatformBilling',
    'Ordering', 'Fulfillment', 'Delivery', 'Returns', 'Finance', 'Sync', 'Reporting',
];

foreach ($modules as $module) {
    foreach ($modules as $other) {
        if ($module === $other) {
            continue;
        }

        arch("{$module} does not import {$other} models")
            ->expect("Modules\\{$module}")
            ->not->toUse("Modules\\{$other}\\Domain\\Models")
            ->group('arch');
    }
}

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

arch('every channel controller is behind the channel guard')
    ->expect('Modules\\*\\Presentation\\Http\\Controllers\\Channel')
    ->toUseMiddleware('auth:channel')
    ->group('arch');

arch('every platform controller is behind the platform guard')
    ->expect('Modules\\*\\Presentation\\Http\\Controllers\\Platform')
    ->toUseMiddleware('auth:platform')
    ->group('arch');

arch('app stays thin')
    ->expect('App')
    ->not->toUse('Illuminate\\Database\\Eloquent\\Model')
    ->group('arch');

arch('domain models stay inside their module')
    ->expect('Modules\\*\\Domain\\Models')
    ->toOnlyBeUsedIn(['Modules\\*\\Domain', 'Modules\\*\\Application', 'Modules\\*\\Infrastructure'])
    ->group('arch');
