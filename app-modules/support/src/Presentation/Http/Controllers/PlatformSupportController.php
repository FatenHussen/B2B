<?php

declare(strict_types=1);

namespace Modules\Support\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Core\Http\ApiController;
use Modules\Support\Domain\Models\SupportTicket;

final class PlatformSupportController extends ApiController
{
    public function search(Request $request): JsonResponse
    {
        $q = (string) $request->query('q', '');
        $users = [];
        if ($q !== '') {
            $users = DB::table('app_users')
                ->where('phone', 'like', '%'.$q.'%')
                ->orWhere('name', 'like', '%'.$q.'%')
                ->limit(25)
                ->get(['id', 'name', 'phone'])
                ->map(fn ($u) => ['type' => 'app_user', 'id' => $u->id, 'name' => $u->name, 'phone' => $u->phone])
                ->all();
        }

        return $this->ok(['results' => $users]);
    }

    public function showUser(string $type, int $id): JsonResponse
    {
        return $this->ok([
            'type' => $type,
            'id' => $id,
            'profile' => DB::table('app_users')->where('id', $id)->first(),
            'orders_count' => 0,
            'open_tickets' => 0,
        ]);
    }

    public function impersonate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_type' => ['required', 'string'],
            'user_id' => ['required', 'integer'],
            'write_enabled' => ['sometimes', 'boolean'],
        ]);

        return $this->ok([
            'token' => 'impersonation-stub',
            'expires_in' => 900,
            'write_enabled' => (bool) ($data['write_enabled'] ?? false),
        ]);
    }

    public function resendOtp(int $id): JsonResponse
    {
        return $this->ok(['user_id' => $id, 'resent' => true]);
    }

    public function revokeSessions(int $id): JsonResponse
    {
        DB::table('personal_access_tokens')->where('tokenable_id', $id)->delete();

        return $this->ok(['user_id' => $id, 'revoked' => true]);
    }

    public function tickets(Request $request): JsonResponse
    {
        $rows = SupportTicket::query()->orderByDesc('id')->limit(100)->get()->map(fn (SupportTicket $t) => [
            'id' => $t->id,
            'subject' => $t->subject,
            'status' => $t->status,
            'priority' => $t->priority,
        ]);

        return $this->ok($rows->all());
    }

    public function storeTicket(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string'],
            'body' => ['sometimes', 'string'],
            'priority' => ['sometimes', 'string'],
        ]);
        $ticket = new SupportTicket;
        $ticket->fill($data + ['created_by' => $request->user()?->getAuthIdentifier()]);
        $ticket->status = 'open';
        $ticket->save();

        return $this->created(['id' => $ticket->id]);
    }

    public function updateTicket(Request $request, int $id): JsonResponse
    {
        $ticket = SupportTicket::query()->findOrFail($id);
        $data = $request->validate([
            'status' => ['sometimes', 'string'],
            'resolution' => ['sometimes', 'string'],
            'root_cause' => ['sometimes', 'string'],
        ]);
        if (isset($data['status'])) {
            $ticket->status = $data['status'];
        }
        $ticket->fill(collect($data)->except('status')->all())->save();

        return $this->ok(['id' => $ticket->id, 'status' => $ticket->status]);
    }

    public function disableUser(int $id): JsonResponse
    {
        DB::table('app_users')->where('id', $id)->update(['status' => 'suspended', 'updated_at' => now()]);

        return $this->ok(['id' => $id, 'status' => 'suspended']);
    }

    public function resetDevice(int $id): JsonResponse
    {
        DB::table('personal_access_tokens')->where('tokenable_id', $id)->delete();

        return $this->ok(['id' => $id, 'device_reset' => true]);
    }
}
