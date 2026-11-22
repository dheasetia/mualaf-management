<?php

declare(strict_types=1);

namespace App\Domain\Dai\Models;

use App\Domain\Dai\Enums\LanguageProficiency;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $dai_id
 * @property int $language_id
 * @property LanguageProficiency $proficiency
 */
final class DaiLanguage extends Pivot
{
    protected $table = 'dai_languages';

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'proficiency' => LanguageProficiency::class,
        ];
    }
}
