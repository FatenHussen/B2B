<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->in('Unit');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
 * Architecture tests read source files and the route table. They boot the
 * application but never touch the database, so they must not pay for a
 * migration refresh — and must stay runnable without a database at all.
 */
pest()->extend(TestCase::class)
    ->in('Architecture');
