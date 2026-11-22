<?php

declare(strict_types=1);

namespace App\Domain\Platform\Models;

use App\Domain\Platform\Enums\DaiActivityCategory;
use Database\Factories\Platform\DaiActivityTypeFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[UseFactory(DaiActivityTypeFactory::class)]
final class DaiActivityType extends Model
{
    /** @use HasFactory<DaiActivityTypeFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name_ar', 'name_en', 'category', 'requires_verification', 'active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'category' => DaiActivityCategory::class,
            'requires_verification' => 'boolean',
            'active' => 'boolean',
        ];
    }
}
