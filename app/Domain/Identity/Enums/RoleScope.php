<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

enum RoleScope: string
{
    /** Islamic Center Dammam as platform operator; valid only without tenant context. */
    case Platform = 'platform';

    /** Held within one tenant; valid only in that tenant's context. */
    case Tenant = 'tenant';
}
