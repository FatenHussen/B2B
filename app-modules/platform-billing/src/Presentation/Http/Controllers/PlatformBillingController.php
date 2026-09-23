<?php

declare(strict_types=1);

namespace Modules\PlatformBilling\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ChannelDirectory;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Contracts\RequestsDualApproval;
use Modules\Core\Http\ApiController;
use Modules\PlatformBilling\Domain\Models\ChannelSubscription;
use Modules\PlatformBilling\Domain\Models\PlatformInvoice;

final class PlatformBillingController extends ApiController
{
    public function subscriptions(Request $request, ChannelDirectory $channels): JsonResponse
    {
        $query = ChannelSubscription::query()->orderByDesc('id');
        $status = $request->input('filter.status') ?? $request->input('filter[status]');
        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        $rows = $query->limit(100)->get()->map(function (ChannelSubscription $s) use ($channels) {
            $planKey = DB::table('channel_plans')->where('id', $s->plan_id)->value('key');

            return [
                'channel' => ['id' => $s->channel_id, 'name' => $channels->name((int) $s->channel_id)],
                'plan' => $planKey,
                'cycle' => $s->cycle,
                'next_renewal' => $s->next_renewal_at?->toDateString(),
                'amount' => $s->amount,
                'status' => $s->status,
            ];
        });

        return $this->ok($rows->all());
    }

    public function assignPlan(Request $request, int $id, RecordsAudit $audit): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:channel_plans,id'],
            'cycle' => ['required', 'in:monthly,yearly'],
            'effective_from' => ['sometimes', 'date'],
            'reason' => ['required', 'string', 'min:3'],
        ]);

        if (! DB::table('supply_channels')->where('id', $id)->exists()) {
            abort(404);
        }

        $plan = DB::table('channel_plans')->where('id', (int) $data['plan_id'])->first();
        $effective = isset($data['effective_from']) ? Carbon::parse($data['effective_from']) : now();
        $scheduled = $effective->isFuture();
        $amount = $data['cycle'] === 'yearly' ? (int) $plan->price_yearly : (int) $plan->price_monthly;

        $sub = new ChannelSubscription;
        $sub->fill([
            'channel_id' => $id,
            'plan_id' => (int) $plan->id,
            'cycle' => $data['cycle'],
            'starts_at' => $scheduled ? null : now(),
            'next_renewal_at' => $scheduled ? null : now()->addYear(),
            'amount' => $amount,
            'scheduled_plan_id' => $scheduled ? (int) $plan->id : null,
            'effective_from' => $effective,
        ]);
        $sub->status = $scheduled ? 'scheduled' : 'active';
        $sub->save();

        DB::table('supply_channels')->where('id', $id)->update([
            'plan_id' => $scheduled ? DB::raw('plan_id') : (int) $plan->id,
            'subscription_status' => $scheduled ? 'scheduled' : 'active',
            'updated_at' => now(),
        ]);

        if (! $scheduled) {
            DB::table('supply_channels')->where('id', $id)->update(['plan_id' => (int) $plan->id]);
        }

        $audit->record('subscription.assigned', $request->user(), ChannelSubscription::class, (int) $sub->id, [
            'reason' => $data['reason'],
            'channel_id' => $id,
        ]);

        return $this->ok(['subscription_id' => $sub->id, 'status' => $sub->status]);
    }

    public function bulkPreview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel_ids' => ['required', 'array', 'max:50'],
            'channel_ids.*' => ['integer'],
            'plan_id' => ['required', 'integer', 'exists:channel_plans,id'],
        ]);
        $plan = DB::table('channel_plans')->where('id', (int) $data['plan_id'])->first();
        $next = count($data['channel_ids']) * (int) $plan->price_monthly;

        return $this->ok([
            'channel_count' => count($data['channel_ids']),
            'mrr_current' => 0,
            'mrr_new' => $next,
            'delta' => $next,
        ]);
    }

    public function bulkApply(Request $request, RecordsAudit $audit): JsonResponse
    {
        $data = $request->validate([
            'channel_ids' => ['required', 'array', 'max:50'],
            'channel_ids.*' => ['integer'],
            'plan_id' => ['required', 'integer', 'exists:channel_plans,id'],
            'cycle' => ['required', 'in:monthly,yearly'],
            'reason' => ['required', 'string', 'min:3'],
        ]);

        $updated = [];
        foreach ($data['channel_ids'] as $channelId) {
            $req = Request::create('/', 'POST', [
                'plan_id' => $data['plan_id'],
                'cycle' => $data['cycle'],
                'reason' => $data['reason'],
            ]);
            $req->setUserResolver(fn () => $request->user());
            $this->assignPlan($req, (int) $channelId, $audit);
            $updated[] = (int) $channelId;
        }

        return $this->ok(['updated' => $updated]);
    }

    public function invoices(Request $request, ChannelDirectory $channels): JsonResponse
    {
        $query = PlatformInvoice::query()->orderByDesc('id');
        $status = $request->input('filter.status') ?? $request->input('filter[status]');
        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        $rows = $query->limit(100)->get()->map(fn (PlatformInvoice $i) => [
            'id' => $i->id,
            'no' => $i->no,
            'channel' => ['id' => $i->channel_id, 'name' => $channels->name((int) $i->channel_id)],
            'amount' => $i->amount,
            'issued_at' => $i->issued_at?->toDateString(),
            'due_at' => $i->due_at?->toDateString(),
            'status' => $i->status,
            'pdf_url' => $i->pdf_path,
        ]);

        return $this->ok($rows->all());
    }

    public function waive(Request $request, int $id, RequestsDualApproval $dual, RecordsAudit $audit): JsonResponse
    {
        $invoice = PlatformInvoice::query()->findOrFail($id);
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3'],
            'approval_request_id' => ['sometimes', 'integer'],
            'approval_reason' => ['required_with:approval_request_id', 'string'],
        ]);

        $payload = ['invoice_id' => $id, 'reason' => $data['reason']];
        $decision = $dual->gate(
            $request->user(),
            'ad.billing.waive',
            'platform_invoice.waive',
            $payload,
            isset($data['approval_request_id']) ? (int) $data['approval_request_id'] : null,
            $data['approval_reason'] ?? null,
        );

        if (! $decision->execute) {
            return $this->ok(['approval_request_id' => $decision->approvalRequestId]);
        }

        $invoice->status = 'waived';
        $invoice->save();
        $audit->record('platform_invoice.waived', $request->user(), PlatformInvoice::class, $id, ['reason' => $data['reason']]);

        return $this->ok(['status' => 'waived']);
    }

    public function creditNote(Request $request, int $id, RecordsAudit $audit): JsonResponse
    {
        $invoice = PlatformInvoice::query()->findOrFail($id);
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:3'],
        ]);

        DB::table('platform_credit_notes')->insert([
            'platform_invoice_id' => $invoice->id,
            'amount' => (int) $data['amount'],
            'reason' => $data['reason'],
            'created_by' => $request->user()?->getAuthIdentifier(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $invoice->status = 'credited';
        $invoice->save();
        $audit->record('platform_invoice.credit_note', $request->user(), PlatformInvoice::class, $id, $data);

        return $this->ok(['status' => 'credited']);
    }

    public function dunning(ChannelDirectory $channels): JsonResponse
    {
        $rows = PlatformInvoice::query()->where('status', 'overdue')->get()->map(fn (PlatformInvoice $i) => [
            'channel' => ['id' => $i->channel_id, 'name' => $channels->name((int) $i->channel_id)],
            'amount' => $i->amount,
            'days_late' => $i->due_at ? max(0, (int) $i->due_at->diffInDays(now())) : 0,
            'reminders_sent' => 0,
            'next_action' => 'suspend_warning',
        ]);

        return $this->ok($rows->all());
    }

    public function revenue(): JsonResponse
    {
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $start = now()->subMonths($i)->startOfMonth();
            $end = (clone $start)->endOfMonth();
            $sum = (int) PlatformInvoice::query()
                ->whereBetween('issued_at', [$start, $end])
                ->whereIn('status', ['paid', 'open', 'overdue'])
                ->sum('amount');
            $months[] = ['month' => $start->format('Y-m'), 'amount' => $sum];
        }

        return $this->ok(['months' => $months]);
    }
}
