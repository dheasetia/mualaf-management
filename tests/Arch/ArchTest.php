<?php

declare(strict_types=1);

arch('application code uses strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('no debugging helpers are left in application code')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

arch('domain models do not depend on Filament resources')
    ->expect('App\Domain')
    ->not->toUse('App\Filament');
