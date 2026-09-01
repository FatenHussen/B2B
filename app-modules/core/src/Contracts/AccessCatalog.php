<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface AccessCatalog
{
    /**
     * @return list<string>
     */
    public function permissionsFor(object $user): array;

    /**
     * @return list<string>
     */
    public function rolesFor(object $user): array;

    /**
     * @return list<string>
     */
    public function permissionsForAppKind(string $kind): array;
}
