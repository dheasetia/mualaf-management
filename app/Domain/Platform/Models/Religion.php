<?php

declare(strict_types=1);

namespace App\Domain\Platform\Models;

use Database\Factories\Platform\ReligionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[UseFactory(ReligionFactory::class)]
final class Religion extends Model
{
    /** @use HasFactory<ReligionFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name_ar', 'name_en', 'active', 'sort_order'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}
