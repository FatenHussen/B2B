<?php

declare(strict_types=1);

namespace Modules\Access\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Access\Application\Actions\ApproveRole;
use Modules\Access\Application\Actions\ApproveTempGrant;
use Modules\Access\Application\Actions\AssignRoles;
use Modules\Access\Application\Actions\CreateRole;
use Modules\Access\Application\Actions\DecideApprovalRequest;
use Modules\Access\Application\Actions\DecideReviewItem;
use Modules\Access\Application\Actions\ReplaceRolePermissions;
use Modules\Access\Application\Actions\RequestTempGrant;
use Modules\Access\Application\Actions\RevokeRoleAssignment;
use Modules\Access\Application\Actions\StartAccessReview;
use Modules\Access\Application\Queries\ListApprovalRequests;
use Modules\Access\Application\Queries\ListPermissionHolders;
use Modules\Access\Application\Queries\ListPermissions;
use Modules\Access\Application\Queries\ListRoles;
use Modules\Access\Application\Queries\ListSodRules;
use Modules\Access\Application\Queries\PreviewRole;
use Modules\Access\Application\Queries\ShowAccessReview;
use Modules\Access\Application\Queries\SimulateAuthorization;
use Modules\Access\Presentation\Http\Requests\ApproveRoleRequest;
use Modules\Access\Presentation\Http\Requests\AssignRolesRequest;
use Modules\Access\Presentation\Http\Requests\CreateRoleRequest;
use Modules\Access\Presentation\Http\Requests\DecideRequest;
use Modules\Access\Presentation\Http\Requests\DecideReviewItemRequest;
use Modules\Access\Presentation\Http\Requests\PreviewRoleRequest;
use Modules\Access\Presentation\Http\Requests\ReplaceRolePermissionsRequest;
use Modules\Access\Presentation\Http\Requests\RequestTempGrantRequest;
use Modules\Access\Presentation\Http\Requests\RevokeRoleAssignmentRequest;
use Modules\Access\Presentation\Http\Requests\SimulateAuthorizationRequest;
use Modules\Access\Presentation\Http\Requests\StartAccessReviewRequest;
use Modules\Core\Http\ApiController;

final class IamController extends ApiController
{
    public function permissions(Request $request, ListPermissions $query): JsonResponse
    {
        return $query($request);
    }

    public function holders(string $code, ListPermissionHolders $query): JsonResponse
    {
        return $this->ok($query($code));
    }

    public function roles(Request $request, ListRoles $query): JsonResponse
    {
        return $query($request);
    }

    public function storeRole(CreateRoleRequest $request, CreateRole $action): JsonResponse
    {
        return $this->created($action($request->user(), $request->validated()));
    }

    public function approveRole(ApproveRoleRequest $request, int $id, ApproveRole $action): JsonResponse
    {
        return $this->ok($action($request->user(), $id, $request->validated()));
    }

    public function replacePermissions(ReplaceRolePermissionsRequest $request, int $id, ReplaceRolePermissions $action): JsonResponse
    {
        return $this->ok($action($request->user(), $id, $request->validated()));
    }

    public function assign(AssignRolesRequest $request, AssignRoles $action): JsonResponse
    {
        return $this->ok($action($request->user(), $request->validated()));
    }

    public function revoke(RevokeRoleAssignmentRequest $request, RevokeRoleAssignment $action): JsonResponse
    {
        return $this->ok($action($request->user(), $request->validated()));
    }

    public function simulate(SimulateAuthorizationRequest $request, SimulateAuthorization $query): JsonResponse
    {
        return $this->ok($query($request->validated()));
    }

    public function requestTempGrant(RequestTempGrantRequest $request, RequestTempGrant $action): JsonResponse
    {
        return $this->ok($action($request->user(), $request->validated()));
    }

    public function approveTempGrant(DecideRequest $request, int $id, ApproveTempGrant $action): JsonResponse
    {
        return $this->ok($action($request->user(), $id, $request->validated()));
    }

    public function sodRules(ListSodRules $query): JsonResponse
    {
        return $this->ok($query());
    }

    public function startReview(StartAccessReviewRequest $request, StartAccessReview $action): JsonResponse
    {
        return $this->ok($action($request->user(), $request->validated()));
    }

    public function approvalInbox(Request $request, ListApprovalRequests $query): JsonResponse
    {
        return $query($request);
    }

    public function decideApproval(DecideRequest $request, int $id, DecideApprovalRequest $action): JsonResponse
    {
        return $this->ok($action($request->user(), $id, $request->validated()));
    }

    public function previewRole(PreviewRoleRequest $request, PreviewRole $query): JsonResponse
    {
        return $this->ok($query($request->validated()));
    }

    public function showReview(int $id, ShowAccessReview $query): JsonResponse
    {
        return $this->ok($query($id));
    }

    public function decideReviewItem(DecideReviewItemRequest $request, int $id, int $itemId, DecideReviewItem $action): JsonResponse
    {
        return $this->ok($action($request->user(), $id, $itemId, $request->validated()));
    }
}
