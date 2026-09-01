<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    protected bool $skipIdempotencyKey = false;

    public function withoutIdempotencyKey(): static
    {
        $this->skipIdempotencyKey = true;

        return $this;
    }

    public function json($method, $uri, array $data = [], array $headers = [], $options = 0): TestResponse
    {
        $verb = strtoupper((string) $method);

        if (
            ! $this->skipIdempotencyKey
            && in_array($verb, ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            && ! $this->hasIdempotencyHeader($headers)
        ) {
            $headers['X-Idempotency-Key'] = (string) Str::uuid();
        }

        $this->skipIdempotencyKey = false;

        return parent::json($method, $uri, $data, $headers, $options);
    }

    /**
     * @param  array<string, mixed>  $headers
     */
    private function hasIdempotencyHeader(array $headers): bool
    {
        foreach (array_keys($headers) as $name) {
            if (strcasecmp((string) $name, 'X-Idempotency-Key') === 0
                || strcasecmp((string) $name, 'Idempotency-Key') === 0) {
                return true;
            }
        }

        foreach (array_keys($this->defaultHeaders) as $name) {
            if (strcasecmp((string) $name, 'X-Idempotency-Key') === 0
                || strcasecmp((string) $name, 'Idempotency-Key') === 0) {
                return true;
            }
        }

        return false;
    }
}
