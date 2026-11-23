<?php

declare(strict_types=1);

namespace App\Domain\Platform\Models;

use Database\Factories\Platform\LanguageFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UseFactory(LanguageFactory::class)]
final class Language extends Model
{
    /** @use HasFactory<LanguageFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = ['code', 'name_ar', 'name_en', 'is_rtl'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_rtl' => 'boolean',
        ];
    }
}
