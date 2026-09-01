<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Testing\TestResponse;

final class CatalogAssert
{
    /**
     * @param  list<string>  $dataKeys
     */
    public static function ok(TestResponse $response, array $dataKeys = [], int $status = 200): TestResponse
    {
        $response->assertStatus($status)->assertJsonStructure(['data', 'meta' => ['server_time']]);

        foreach ($dataKeys as $key) {
            $response->assertJsonPath('data.'.$key, fn ($value) => $value !== null);
        }

        return $response;
    }

    public static function error(TestResponse $response, int $status, string $code): TestResponse
    {
        return $response
            ->assertStatus($status)
            ->assertJsonPath('error.code', $code);
    }
}
