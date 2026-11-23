<?php

declare(strict_types=1);

namespace App\Support\Localization;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Picks name_en or name_ar of a record for the active locale. Arabic is the
 * fallback where an English name is optional (companies, programs,
 * submission types). Official documents keep their Arabic names.
 */
final class LocalizedName
{
    public static function column(): string
    {
        return app()->getLocale() === 'en' ? 'name_en' : 'name_ar';
    }

    public static function of(?Model $model): ?string
    {
        if ($model === null) {
            return null;
        }

        $name = $model->getAttribute(self::column());

        if (blank($name)) {
            $name = $model->getAttribute('name_ar');
        }

        return blank($name) ? null : (string) $name;
    }

    /**
     * Select options keyed by id; sorted by the shown name unless the query
     * already has its own order (sort_order, code).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return array<int|string, string>
     */
    public static function options(Builder $query, bool $sortByName = true): array
    {
        $options = $query->get()
            ->mapWithKeys(fn (Model $model): array => [$model->getKey() => (string) self::of($model)]);

        return ($sortByName ? $options->sort(SORT_NATURAL | SORT_FLAG_CASE) : $options)->all();
    }

    /**
     * Names by id, for lookups such as dashboards and exports.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return array<int|string, string>
     */
    public static function map(Builder $query): array
    {
        return self::options($query, sortByName: false);
    }
}
