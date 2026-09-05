<?php

declare(strict_types=1);

namespace Modules\Access\Application\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

final class PageSize
{
    public static function perPage(Request $request, int $default = 25, int $max = 100): int
    {
        $value = (int) $request->integer('per_page', $default);

        return max(1, min($max, $value));
    }

    /**
     * @template T
     *
     * @param  list<T>  $items
     * @return LengthAwarePaginator<int, T>
     */
    public static function paginate(array $items, Request $request): LengthAwarePaginator
    {
        $perPage = self::perPage($request);
        $page = max(1, (int) $request->integer('page', 1));
        $total = count($items);
        $slice = array_slice($items, ($page - 1) * $perPage, $perPage);

        return new Paginator($slice, $total, $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
    }
}
