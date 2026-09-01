<?php

declare(strict_types=1);

namespace Modules\Access\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Access\Application\Actions\ExportAuditLog;
use Modules\Access\Application\Queries\ListAuditLogs;
use Modules\Access\Presentation\Http\Requests\ExportAuditRequest;
use Modules\Core\Http\ApiController;

final class AuditController extends ApiController
{
    public function index(Request $request, ListAuditLogs $query): JsonResponse
    {
        return $query($request);
    }

    public function export(ExportAuditRequest $request, ExportAuditLog $action): JsonResponse
    {
        return $this->ok($action($request->user(), $request->validated()));
    }
}
