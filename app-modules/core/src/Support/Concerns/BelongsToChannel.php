<?php

namespace Modules\Core\Support\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Domain\Exceptions\MissingChannelScopeException;
use Modules\Core\Support\Tenant;

trait BelongsToChannel
{
    protected static function bootBelongsToChannel(): void
    {
        static::addGlobalScope('channel', function (Builder $query) {
            if (Tenant::isUnscoped()) {
                return;
            }

            $id = Tenant::currentId();

            if ($id === null) {
                throw MissingChannelScopeException::make($query->getModel()::class);
            }

            $query->where(
                $query->getModel()->qualifyColumn('supply_channel_id'),
                $id
            );
        });

        static::creating(function ($model) {
            $model->supply_channel_id ??= Tenant::currentId();
        });
    }

    public function scopeAcrossChannels(Builder $query): Builder
    {
        return $query->withoutGlobalScope('channel');
    }
}
