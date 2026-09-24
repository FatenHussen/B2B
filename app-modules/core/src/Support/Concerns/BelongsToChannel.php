<?php

namespace Modules\Core\Support\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Domain\Exceptions\MissingChannelScopeException;
use Modules\Core\Support\Tenant;

/**
 * Automatic per-channel isolation, rule 10.
 *
 * The column is read from `$channelColumn` rather than hardcoded. Both names are live in
 * this repository — `supply_channel_id` on sixteen models and `channel_id` on the rest —
 * and until this trait could name the second one, applying it to a `channel_id` table
 * produced a query against a column that does not exist. A model on the other spelling
 * declares it:
 *
 *     protected string $channelColumn = 'channel_id';
 *
 * The default is `supply_channel_id`, so the models already using the trait declare
 * nothing and behave exactly as before.
 *
 * Unifying the two names into one column is a separate, deliberately deferred ticket:
 * the migration would rewrite a dozen tables and every query and index that names them,
 * which costs more today than the inconsistency does.
 */
trait BelongsToChannel
{
    /**
     * This model's channel column, defaulting when the model does not declare one.
     *
     * The trait deliberately declares no `$channelColumn` property of its own. PHP treats
     * a trait property with an initialiser and a class property of the same name with a
     * different value as an incompatible composition and fails at compile time — so a
     * model could not have overridden it. The property lives only on the models that need
     * the second spelling, and this reads it reflectively when it is there.
     *
     * `get_object_vars`, not `property_exists`: the analyser evaluates a trait in the
     * context of every class using it, and on a class that declares the property
     * `property_exists($this, …)` is "always true" — one finding per relaxed model,
     * which was six the day the three platform-written records joined.
     */
    public function channelColumn(): string
    {
        $declared = get_object_vars($this)['channelColumn'] ?? null;

        return is_string($declared) && $declared !== '' ? $declared : 'supply_channel_id';
    }

    /**
     * Whether this model tolerates a query with no tenant set.
     *
     * Strict is the default and the one to reach for: no tenant means a programming
     * error, and the exception says so at the point it happens instead of returning a
     * silently empty result.
     *
     * A model declares `protected bool $channelScopeOptional = true;` in two cases.
     *
     * 1. It is read in order to *decide* which channel a caller belongs to, before any
     *    tenant exists. `ChannelUserChannel` is the clearest: `ResolveTenant` calls
     *    `ChannelUser::defaultChannelId()`, which reads that table to find the tenant, so
     *    a scope demanding the tenant to read the table that supplies it cannot terminate.
     *
     * 2. It is a record the platform writes *about* a channel and the channel may read
     *    later — a subscription, a platform invoice, a manager invite. Its writer runs on
     *    `/platform/*`, where no tenant is set, so strict mode would throw on every
     *    back-office screen; a channel route added tomorrow to read the same table is
     *    isolated the day it lands, with no `where` to remember. The writer sets
     *    `channel_id` explicitly; the `creating` hook fills it from the tenant only when
     *    it is missing, and on these tables the column is NOT NULL, so a row written with
     *    neither fails at the constraint rather than silently.
     *
     * Relaxed is not unscoped. With a tenant set the filter applies exactly as it does in
     * strict mode; only the absence of one is tolerated. Prefer relaxed over exemption
     * whenever absence of a tenant is the only reason a table cannot be strict —
     * exemption filters never. Tables that must stay cross-channel even with a tenant
     * set (audit log, zone lookup, channel applications) remain in
     * `CHANNEL_SCOPE_EXEMPT` with a written reason.
     */
    public function channelScopeOptional(): bool
    {
        return (get_object_vars($this)['channelScopeOptional'] ?? false) === true;
    }

    protected static function bootBelongsToChannel(): void
    {
        static::addGlobalScope('channel', function (Builder $query) {
            if (Tenant::isUnscoped()) {
                return;
            }

            $id = Tenant::currentId();
            $model = $query->getModel();

            if ($id === null) {
                // Relaxed models pass through unfiltered rather than throwing; see
                // channelScopeOptional(). Strict models — the default — treat a missing
                // tenant as the programming error it is.
                if ($model->channelScopeOptional()) {
                    return;
                }

                throw MissingChannelScopeException::make($model::class);
            }

            $query->where(
                $model->qualifyColumn($model->channelColumn()),
                $id
            );
        });

        static::creating(function ($model) {
            $column = $model->channelColumn();

            $model->{$column} ??= Tenant::currentId();
        });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeAcrossChannels(Builder $query): Builder
    {
        return $query->withoutGlobalScope('channel');
    }
}
