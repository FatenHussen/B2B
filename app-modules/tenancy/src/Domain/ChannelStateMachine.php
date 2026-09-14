<?php

declare(strict_types=1);

namespace Modules\Tenancy\Domain;

use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Tenancy\Domain\Enums\ChannelStatus;

/**
 * Which channel states may follow which — and nothing else.
 *
 * This class answers one question and holds no state of its own. It does not write
 * the status, record the transition or emit anything: that is `ChannelLifecycle`, the
 * only caller allowed to act on the answer. Keeping the two apart is deliberate — see
 * BE-O15 for what happens when a machine only checks and every action writes for itself.
 *
 * The matrix is keyed by *state*, not by action, because that is how the contract
 * speaks: EP-AD-054 takes `to_status` and returns `allowed_next` as a list of states.
 */
final class ChannelStateMachine
{
    /**
     * The transition matrix, state → states that may follow it.
     *
     * Three sources agree on it and each edge is named by at least one of them:
     *
     * - `provisioning → active`     BE-T05 §2: on success the channel moves to active.
     *                               Failure records the error and stays retryable — the
     *                               channel remains `provisioning`; there is no `failed`.
     * - `active → suspended`        EP-AD-054 (`to_status: suspended`); BE-T13 §2 and
     *                               BR-AD-14: suspension freezes new orders only, and
     *                               only an active channel has new orders to freeze.
     * - `suspended → active`        EP-AD-054 `allowed_next: ['active', 'archived']`;
     *                               DOC-08 `ad.channels.suspend` "إيقاف قناة أو رفع الإيقاف".
     * - `suspended → archived`      the same EP-AD-054 response.
     * - `archived → ∅`              nothing names an exit. BE-T15 deletes *from* archived,
     *                               and deletion is a request, not a status.
     *
     * `active → archived` is absent on purpose (BE-T01 §2). A running channel is suspended
     * first — which is what stops new orders — and archived from there. Draw the edge and
     * a client will draw the button.
     *
     * A state's own name is never in its list: moving to where you already are is not a
     * transition, and answering 200 to it would log an event that changed nothing.
     *
     * @var array<string, list<string>>
     */
    public const MATRIX = [
        'provisioning' => ['active'],
        'active' => ['suspended'],
        'suspended' => ['active', 'archived'],
        'archived' => [],
    ];

    /**
     * The states that may follow `$from`, in the order the client should draw them.
     *
     * @return list<ChannelStatus>
     */
    public function allowedNext(ChannelStatus $from): array
    {
        return array_map(
            static fn (string $status): ChannelStatus => ChannelStatus::from($status),
            self::MATRIX[$from->value] ?? [],
        );
    }

    public function can(ChannelStatus $from, ChannelStatus $to): bool
    {
        return in_array($to->value, self::MATRIX[$from->value] ?? [], true);
    }

    /**
     * @throws DomainException 409 `illegal_transition`, with the states and what would
     *                         have been allowed in `details`
     */
    public function assert(ChannelStatus $from, ChannelStatus $to): void
    {
        if ($this->can($from, $to)) {
            return;
        }

        throw DomainException::of(ErrorCode::IllegalTransition, __('tenancy.illegal_transition'), [
            'from' => $from->value,
            'to' => $to->value,
            'allowed_next' => array_map(static fn (ChannelStatus $s) => $s->value, $this->allowedNext($from)),
        ]);
    }
}
