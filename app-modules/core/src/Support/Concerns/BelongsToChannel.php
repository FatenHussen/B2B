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
     */
    public function channelColumn(): string
    {
        return property_exists($this, 'channelColumn')
            ? $this->channelColumn
            : 'supply_channel_id';
    }

    /**
     * Whether this model tolerates a query with no tenant set.
     *
     * Strict is the default and the one to reach for: no tenant means a programming
     * error, and the exception says so at the point it happens instead of returning a
     * silently empty result.
     *
     * A model declares `protected bool $channelScopeOptional = true;` when it is read in
     * order to *decide* which channel a caller belongs to — before any tenant exists.
     * `ChannelUserChannel` is the clearest: `ResolveTenant` calls
     * `ChannelUser::defaultChannelId()`, which reads that table to find the tenant, so a
     * scope demanding the tenant to read the table that supplies it cannot terminate.
     *
     * Relaxed is not unscoped. With a tenant set the filter applies exactly as it does in
     * strict mode; only the absence of one is tolerated. That makes it strictly better
     * than leaving such a model untraited, which is what these three were before.
     */
    public function channelScopeOptional(): bool
    {
        return property_exists($this, 'channelScopeOptional')
            ? $this->channelScopeOptional
            : false;
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

    public function scopeAcrossChannels(Builder $query): Builder
    {
        return $query->withoutGlobalScope('channel');
    }
}
