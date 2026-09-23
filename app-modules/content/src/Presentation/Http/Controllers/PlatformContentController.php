<?php

declare(strict_types=1);

namespace Modules\Content\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Content\Application\Actions\CreateHelpGuide;
use Modules\Content\Application\Actions\PublishLegalDocument;
use Modules\Content\Application\Actions\UpdatePlatformIntro;
use Modules\Content\Application\Queries\ListHelpGuides;
use Modules\Content\Application\Queries\ListLegalDocuments;
use Modules\Content\Application\Queries\ShowPlatformIntro;
use Modules\Content\Presentation\Http\Requests\PublishLegalDocumentRequest;
use Modules\Content\Presentation\Http\Requests\StoreHelpGuideRequest;
use Modules\Content\Presentation\Http\Requests\UpdateIntroRequest;
use Modules\Core\Http\ApiController;

final class PlatformContentController extends ApiController
{
    public function showIntro(ShowPlatformIntro $query): JsonResponse
    {
        return $this->ok($query());
    }

    public function updateIntro(UpdateIntroRequest $request, UpdatePlatformIntro $action): JsonResponse
    {
        return $this->ok($action($request->validated()));
    }

    public function legal(Request $request, ListLegalDocuments $query): JsonResponse
    {
        return $this->ok($query($request));
    }

    public function publishLegal(PublishLegalDocumentRequest $request, PublishLegalDocument $action): JsonResponse
    {
        /** @var object $actor */
        $actor = $request->user();

        return $this->created($action($request->validated(), $actor));
    }

    public function help(Request $request, ListHelpGuides $query): JsonResponse
    {
        return $this->ok($query($request));
    }

    public function storeHelp(StoreHelpGuideRequest $request, CreateHelpGuide $action): JsonResponse
    {
        return $this->created($action($request->validated()));
    }
}
