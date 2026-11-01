<?php

declare(strict_types=1);

/**
 * BF-22. The sixth gate: live `/api/v1` routes and the published OpenAPI document
 * agree on method, path, and guard. A response-body change is still a contract
 * change — this command does not read bodies — and is stated in the pull request.
 */
it('matches live api routes to the openapi document', function () {
    $this->artisan('openapi:generate', ['--check' => true])->assertSuccessful();
})->group('arch');
