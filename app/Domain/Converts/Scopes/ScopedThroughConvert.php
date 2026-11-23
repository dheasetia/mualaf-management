<?php

declare(strict_types=1);

namespace App\Domain\Converts\Scopes;

use App\Domain\Converts\Models\Convert;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For convert child tables (identifiers, contacts, ...): rows are visible only
 * through a convert the current office may see.
 *
 * @mixin Model
 */
trait ScopedThroughConvert
{
    public static function bootScopedThroughConvert(): void
    {
        static::addGlobalScope('through_convert', function (Builder $builder): void {
            if (! app(TenantContext::class)->isBypassed()) {
                $builder->whereHas('convert');
            }
        });
    }

    /** @return BelongsTo<Convert, $this> */
    public function convert(): BelongsTo
    {
        return $this->belongsTo(Convert::class);
    }
}
