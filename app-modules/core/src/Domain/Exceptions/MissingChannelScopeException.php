<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

final class MissingChannelScopeException extends DomainException
{
    public static function make(string $model): self
    {
        return new self(
            "Query on {$model} ran without a channel scope. Use Tenant::set(), Tenant::as(), or Tenant::withoutScope().",
            'channel_scope_required',
            500,
        );
    }
}
