<?php

declare(strict_types=1);

namespace Modules\Tenancy\Application\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Contracts\RequestsDualApproval;
use Modules\Core\Contracts\VerifiesPlatformStepUpOtp;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\Tenant;
use Modules\Tenancy\Domain\Enums\ChannelStatus;
use Modules\Tenancy\Domain\Models\ChannelEvent;
use Modules\Tenancy\Domain\Models\SupplyChannel;

/**
 * PA-18 / EP-AD-058 / BF-05 — channel deletion is no longer immediate. The channel must be
 * archived ≥ 30 days; the caller confirms password + Identity step-up OTP + typed name;
 * a second approver completes the dual gate; then the row is soft-deleted.
 */
final class RequestChannelDeletion
{
    public function __construct(
        private readonly RequestsDualApproval $dual,
        private readonly RecordsAudit $audit,
        private readonly VerifiesPlatformStepUpOtp $stepUpOtp,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{deletion_request_id: int}|array{deletion_request_id: int, deleted: true}
     */
    public function __invoke(SupplyChannel $channel, array $data, object $actor): array
    {
        $this->assertArchivedLongEnough($channel);
        $this->assertTypedName($channel, (string) ($data['typed_name'] ?? ''));
        $this->assertPassword($actor, (string) ($data['password_confirmation'] ?? ''));
        $this->stepUpOtp->verify(
            $actor,
            VerifiesPlatformStepUpOtp::PURPOSE_CHANNEL_DELETE,
            (string) ($data['otp_code'] ?? ''),
        );

        $payload = [
            'channel_id' => (int) $channel->id,
            'typed_name' => (string) $data['typed_name'],
        ];

        return Tenant::as((int) $channel->id, function () use ($channel, $data, $actor, $payload): array {
            $decision = $this->dual->gate(
                $actor,
                'ad.channels.delete',
                'channel.delete',
                $payload,
                isset($data['approval_request_id']) ? (int) $data['approval_request_id'] : null,
                isset($data['approval_reason']) ? (string) $data['approval_reason'] : null,
            );

            if (! $decision->execute) {
                return ['deletion_request_id' => (int) $decision->approvalRequestId];
            }

            $channel->delete();

            $this->audit->record(
                action: 'channel.deleted',
                actor: $actor,
                subjectType: SupplyChannel::class,
                subjectId: (int) $channel->id,
                properties: [
                    'deletion_request_id' => $decision->approvalRequestId,
                    'typed_name' => (string) $data['typed_name'],
                ],
                channelId: (int) $channel->id,
            );

            return [
                'deletion_request_id' => (int) $decision->approvalRequestId,
                'deleted' => true,
            ];
        });
    }

    private function assertArchivedLongEnough(SupplyChannel $channel): void
    {
        if ($channel->status !== ChannelStatus::Archived) {
            throw DomainException::of(ErrorCode::IllegalTransition, __('tenancy.illegal_transition'));
        }

        $archivedAt = ChannelEvent::query()
            ->where('channel_id', $channel->id)
            ->where('to_status', ChannelStatus::Archived->value)
            ->orderByDesc('at')
            ->value('at');

        $since = $archivedAt !== null
            ? Carbon::parse($archivedAt)
            : ($channel->updated_at ?? now());

        if ($since->gt(now()->subDays(30))) {
            throw DomainException::of(ErrorCode::IllegalTransition, __('tenancy.illegal_transition'));
        }
    }

    private function assertTypedName(SupplyChannel $channel, string $typed): void
    {
        if ($typed === '' || $typed !== $channel->name) {
            throw DomainException::of(ErrorCode::ValidationFailed, __('tenancy.delete_name_mismatch'));
        }
    }

    private function assertPassword(object $actor, string $password): void
    {
        $hash = is_object($actor) && isset($actor->password) ? (string) $actor->password : '';
        if ($password === '' || $hash === '' || ! Hash::check($password, $hash)) {
            throw DomainException::of(ErrorCode::RequiresPasswordConfirm, __('identity.requires_password_confirm'));
        }
    }
}
