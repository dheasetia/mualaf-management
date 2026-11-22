<?php

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Database\Eloquent\Model;

/**
 * Third layer of append-only enforcement (05-database-conventions §5.4): models
 * refuse update and delete. The database (grants + triggers) is the real guard,
 * because query-builder writes never reach model events.
 *
 * @mixin Model
 */
trait AppendOnly
{
    public static function bootAppendOnly(): void
    {
        static::updating(static function (Model $model): never {
            throw AppendOnlyViolation::make($model::class, 'update');
        });

        static::deleting(static function (Model $model): never {
            throw AppendOnlyViolation::make($model::class, 'delete');
        });
    }
}
