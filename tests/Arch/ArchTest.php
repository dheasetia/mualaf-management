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

// 01-architecture §1.2: modules talk through Actions, Services, Contracts and Data, not each other's models.
arch('the prospects module does not use da\'i models')
    ->expect('App\Domain\Prospects')
    ->not->toUse('App\Domain\Dai\Models');

arch('the da\'i module does not depend on the prospects module')
    ->expect('App\Domain\Dai')
    ->not->toUse('App\Domain\Prospects');

arch('the converts module does not use da\'i or prospect models')
    ->expect('App\Domain\Converts')
    ->not->toUse(['App\Domain\Dai\Models', 'App\Domain\Prospects\Models']);

arch('the registration module does not use prospect or da\'i models')
    ->expect('App\Domain\Registration')
    ->not->toUse(['App\Domain\Prospects\Models', 'App\Domain\Dai\Models']);

arch('the converts module does not depend on the registration module')
    ->expect('App\Domain\Converts')
    ->not->toUse('App\Domain\Registration');
