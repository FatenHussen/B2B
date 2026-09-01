<?php

namespace Modules\Core\Http;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

abstract class ApiController extends Controller
{
    /**
     * @param  array<string, mixed>  $meta
     */
    protected function ok(mixed $data = null, array $meta = []): JsonResponse
    {
        return ApiResponse::success($data, $meta);
    }

    protected function created(mixed $data = null): JsonResponse
    {
        return ApiResponse::created($data);
    }

    protected function noContent(): JsonResponse
    {
        return ApiResponse::noContent();
    }

    protected function paginated(LengthAwarePaginator|CursorPaginator $paginator, ?callable $transform = null): JsonResponse
    {
        return ApiResponse::paginate($paginator, $transform);
    }
}
