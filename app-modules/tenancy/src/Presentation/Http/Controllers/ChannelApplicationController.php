<?php

declare(strict_types=1);

namespace Modules\Tenancy\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\ApiController;
use Modules\Tenancy\Application\Actions\DecideChannelApplication;
use Modules\Tenancy\Domain\Models\ChannelApplication;
use Modules\Tenancy\Presentation\Http\Requests\DecideChannelApplicationRequest;

final class ChannelApplicationController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = ChannelApplication::query()->orderByDesc('id');

        $status = $request->input('filter.status') ?? $request->input('filter[status]');
        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        $rows = $query->paginate(min(max((int) $request->integer('per_page', 25), 1), 100));

        return $this->paginated($rows, fn (ChannelApplication $app) => [
            'id' => $app->id,
            'name' => $app->name,
            'legal_form' => $app->legal_form,
            'cr_number' => $app->cr_number,
            'status' => $app->status->value,
            'submitted_at' => $app->created_at?->toIso8601String(),
        ]);
    }

    public function decide(
        DecideChannelApplicationRequest $request,
        ChannelApplication $channelApplication,
        DecideChannelApplication $action,
    ): JsonResponse {
        /** @var object $actor */
        $actor = $request->user();

        return $this->ok($action($channelApplication, $request->validated(), $actor));
    }
}
