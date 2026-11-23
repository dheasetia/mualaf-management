<?php

declare(strict_types=1);

namespace App\Domain\Platform\Models;

use Database\Factories\Platform\BudgetCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[UseFactory(BudgetCategoryFactory::class)]
final class BudgetCategory extends Model
{
    /** @use HasFactory<BudgetCategoryFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name_ar', 'name_en', 'active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}
