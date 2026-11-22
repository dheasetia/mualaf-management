<?php

declare(strict_types=1);

namespace App\Domain\Platform\Models;

use Database\Factories\Platform\CountryFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UseFactory(CountryFactory::class)]
final class Country extends Model
{
    /** @use HasFactory<CountryFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = ['iso2', 'iso3', 'name_ar', 'name_en', 'phone_code'];
}
