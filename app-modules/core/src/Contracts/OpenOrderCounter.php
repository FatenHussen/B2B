<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Open sub-orders in a zone, for the impact count EP-AD-034 shows before a zone is
 * disabled.
 *
 * Ordering owns `sub_orders`. Reference renders the confirmation dialog and must not read
 * that table, so it asks for a number and receives a number — never a model, never a row.
 */
interface OpenOrderCounter
{
    /**
     * How many sub-orders in this zone have not reached a terminal state.
     *
     * **Open is defined by negation, deliberately.** A sub-order is open when its status
     * is *not* one of the three terminal states in DOC-01 §4.6.3 — `delivered`,
     * `cancelled`, `rejected`. The positive list is every other state, and it would go
     * stale the first time the lifecycle gains one: a new intermediate state would
     * silently stop being counted, and the dialog would under-report what disabling a
     * zone affects. Stated as a negation, a new state is counted as open until someone
     * deliberately declares it terminal.
     *
     * Counted live on every call. BE-R03 requires it: the impact numbers are never cached
     * and never estimated, because they are shown to justify an irreversible decision.
     */
    public function countOpenInZone(int $zoneId): int;
}
