<?php

namespace Modules\Core\Http;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

/**
 * Unified JSON envelope (DOC-10 §5.5).
 *
 * Success: { data, meta? }
 * Error:   { error: { code, message, details? } }
 */
final class ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function success(mixed $data = null, array $meta = [], int $status = 200): JsonResponse
    {
        $meta = ['server_time' => now()->timezone('Asia/Damascus')->toIso8601String()] + $meta;

        return response()->json(['data' => $data, 'meta' => $meta], $status);
    }

    public static function created(mixed $data = null): JsonResponse
    {
        return self::success($data, [], 201);
    }

    public static function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * $permission names the permission a 403 wanted (DOC-10 §5.5). It sits beside the
     * code rather than inside details because a client reads it directly.
     *
     * @param  array<string, mixed>  $details
     */
    public static function error(string $code, string $message, int $status = 400, array $details = [], ?string $permission = null): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message];

        if ($permission !== null && $permission !== '') {
            $error['permission'] = $permission;
        }

        if ($details !== []) {
            $error['details'] = $details;
        }

        return response()->json(['error' => $error], $status);
    }

    /**
     * @param  array<string, mixed>  $extraMeta
     */
    public static function paginate(LengthAwarePaginator|CursorPaginator $paginator, ?callable $transform = null, array $extraMeta = []): JsonResponse
    {
        $items = collect($paginator->items());

        if ($transform !== null) {
            $items = $items->map($transform);
        }

        if ($paginator instanceof CursorPaginator) {
            $meta = [
                'per_page' => $paginator->perPage(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'prev_cursor' => $paginator->previousCursor()?->encode(),
                'has_more' => $paginator->hasMorePages(),
            ];
        } else {
            $meta = [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ];
        }

        return self::success($items->all(), $meta + $extraMeta);
    }
}
