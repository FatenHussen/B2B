<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * BF-22. The OpenAPI file is the published contract. This command does not
 * rebuild it — `php docs/api/generate.php` does that from the catalog. `--check`
 * fails when a live `/api/v1` route and that file disagree on method, path, or
 * guard. Response bodies stay a review: this gate does not read them.
 */
final class OpenApiGenerateCommand extends Command
{
    protected $signature = 'openapi:generate {--check : Fail when live routes differ from the OpenAPI document}';

    protected $description = 'Check live /api/v1 routes against docs/api/b2b-api.openapi.json';

    public function handle(): int
    {
        $path = base_path('docs/api/b2b-api.openapi.json');
        if (! is_file($path)) {
            $this->error('Missing '.$path.'. Run php docs/api/generate.php first.');

            return self::FAILURE;
        }

        /** @var array{paths?: array<string, array<string, array<string, mixed>>>} $document */
        $document = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $documented = $this->documented($document['paths'] ?? []);
        $live = $this->live();

        $missing = array_diff_key($documented, $live);
        $extra = array_diff_key($live, $documented);
        $guards = [];
        foreach (array_intersect_key($live, $documented) as $key => $routeGuard) {
            if (! $this->guardsAgree($documented[$key], $routeGuard, $key)) {
                $guards[] = $key.' live ['.implode(',', $routeGuard).'] document '.json_encode($documented[$key]);
            }
        }

        if ($missing === [] && $extra === [] && $guards === []) {
            $this->info('OpenAPI matches '.count($live).' live /api/v1 routes (method, path, guard).');

            return self::SUCCESS;
        }

        foreach (array_keys($missing) as $key) {
            $this->error('in the document, not in the route table: '.$key);
        }
        foreach (array_keys($extra) as $key) {
            $this->error('in the route table, not in the document: '.$key);
        }
        foreach ($guards as $line) {
            $this->error('guard: '.$line);
        }

        return self::FAILURE;
    }

    /**
     * @param  array<string, array<string, array<string, mixed>>>  $paths
     * @return array<string, list<string>>
     */
    private function documented(array $paths): array
    {
        $out = [];
        foreach ($paths as $path => $operations) {
            foreach ($operations as $method => $operation) {
                if (! is_array($operation)) {
                    continue;
                }
                $guard = $operation['x-guard'] ?? null;
                $out[$this->key($method, $path)] = $this->expectedGuards(is_string($guard) ? $guard : null);
            }
        }

        return $out;
    }

    /**
     * @return array<string, list<string>>
     */
    private function live(): array
    {
        $routes = RouteFacade::getRoutes()->getRoutes();
        if (! is_array($routes)) {
            return [];
        }

        $out = [];
        foreach ($routes as $route) {
            if (! $route instanceof Route || ! str_starts_with($route->uri(), 'api/v1')) {
                continue;
            }
            $guards = $this->guardsOn($route);
            foreach ($route->methods() as $method) {
                if ($method === 'HEAD') {
                    continue;
                }
                $out[$this->key($method, '/'.$route->uri())] = $guards;
            }
        }

        return $out;
    }

    /**
     * A route with no `auth:` middleware is a login or a public read. Those match
     * a null guard, and a login also matches the surface guard named in the
     * document. A route that names guards must name exactly those.
     *
     * @param  list<string>  $documented
     * @param  list<string>  $live
     */
    private function guardsAgree(array $documented, array $live, string $key): bool
    {
        if ($live === []) {
            return $documented === [] || str_contains($key, '/auth/');
        }

        return $live === $documented;
    }

    /**
     * @return list<string>
     */
    private function expectedGuards(?string $guard): array
    {
        if ($guard === null || $guard === '') {
            return [];
        }
        if ($guard === 'other') {
            return ['app', 'channel', 'platform', 'warehouse'];
        }

        return [$guard];
    }

    /**
     * @return list<string>
     */
    private function guardsOn(Route $route): array
    {
        $guards = [];
        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware) || ! str_starts_with($middleware, 'auth:')) {
                continue;
            }
            foreach (explode(',', substr($middleware, strlen('auth:'))) as $guard) {
                $guard = trim($guard);
                if ($guard !== '') {
                    $guards[] = $guard;
                }
            }
        }
        $guards = array_values(array_unique($guards));
        sort($guards);

        return $guards;
    }

    private function key(string $method, string $path): string
    {
        $path = '/'.ltrim($path, '/');
        $path = preg_replace('#\{[^}]+\}#', '{}', $path) ?? $path;

        return strtoupper($method).' '.rtrim($path, '/');
    }
}
