<?php

declare(strict_types=1);

namespace Modules\Reference\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ChannelDirectory;
use Modules\Core\Contracts\RepDirectory;
use Modules\Core\Contracts\RetailerDirectory;
use Modules\Core\Http\ApiController;
use Modules\Reference\Application\Services\ReferenceMutations;
use Modules\Reference\Domain\Enums\ReferenceEntity;
use Modules\Reference\Domain\Enums\RefStatus;
use Modules\Reference\Domain\Models\ActivityType;
use Modules\Reference\Presentation\Http\Requests\ChangeRefStatusRequest;
use Modules\Reference\Presentation\Http\Requests\StoreActivityTypeRequest;
use Modules\Reference\Presentation\Http\Requests\UpdateActivityTypeRequest;
use Modules\Reference\Presentation\Http\Resources\ActivityTypeResource;

/**
 * BE-R04 — EP-AD-035A, 035B, 042C, 043B.
 *
 * An activity type may carry suggested root categories; they ride the
 * `activity_type_root_category` pivot and surface in `/public/refs` so a retailer is
 * shown the right shelf right after registration.
 */
final class ActivityTypeController extends ApiController
{
    public function __construct(private readonly ReferenceMutations $mutations) {}

    public function index(Request $request): JsonResponse
    {
        $rows = ActivityType::query()
            ->with('suggestedCategories')
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search')->toString().'%'))
            ->when($request->filled('filter.status'), fn ($q) => $q->where('status', $request->input('filter.status')))
            ->orderBy('order')
            ->orderBy('name')
            ->paginate(min((int) $request->integer('per_page', 25), 100));

        return $this->paginated($rows, fn (ActivityType $row) => (new ActivityTypeResource($row))->resolve());
    }

    public function show(ActivityType $activityType): JsonResponse
    {
        return $this->ok(new ActivityTypeResource($activityType->load('suggestedCategories')));
    }

    public function store(StoreActivityTypeRequest $request): JsonResponse
    {
        $row = DB::transaction(function () use ($request): ActivityType {
            /** @var ActivityType $row */
            $row = $this->mutations->create(
                ReferenceEntity::ActivityTypes,
                $request->safe()->except(['reason', 'suggested_category_ids']),
                $request->user(),
                $request->validated('reason'),
            );
            $row->suggestedCategories()->sync($request->validated('suggested_category_ids') ?? []);

            return $row;
        });

        return $this->created(new ActivityTypeResource($row->load('suggestedCategories')));
    }

    public function update(UpdateActivityTypeRequest $request, ActivityType $activityType): JsonResponse
    {
        $row = DB::transaction(function () use ($request, $activityType): ActivityType {
            /** @var ActivityType $row */
            $row = $this->mutations->update(
                ReferenceEntity::ActivityTypes,
                $activityType,
                $request->safe()->except(['reason', 'suggested_category_ids']),
                (string) $request->validated('reason'),
                $request->user(),
            );
            if ($request->has('suggested_category_ids')) {
                $row->suggestedCategories()->sync($request->validated('suggested_category_ids'));
            }

            return $row;
        });

        return $this->ok(new ActivityTypeResource($row->load('suggestedCategories')));
    }

    /**
     * EP-AD-043B — "تعطيله يخفي المرتبط به ولا يحذف حساباً": the retailers and channels
     * on this activity keep their rows; new registrations stop choosing it. Both counts
     * come through Core contracts.
     */
    public function changeStatus(
        ChangeRefStatusRequest $request,
        ActivityType $activityType,
        RetailerDirectory $retailers,
        RepDirectory $reps,
        ChannelDirectory $channels,
    ): JsonResponse {
        $to = RefStatus::from($request->validated('status'));

        return $this->ok($this->mutations->changeStatus(
            ReferenceEntity::ActivityTypes,
            $activityType,
            $activityType->status->value,
            $to->value,
            function () use ($activityType, $to): void {
                $activityType->status = $to;
            },
            (string) $request->validated('reason'),
            $request->user(),
            [
                'retailers' => $retailers->countByActivityType($activityType->id),
                'reps' => $reps->countByActivityType($activityType->id),
                'channels' => $channels->countByActivityType($activityType->id),
            ],
        ));
    }
}
