<?php

declare(strict_types=1);

namespace App\Domain\FollowUp\Models;

use App\Domain\FollowUp\Enums\TriggerEvent;
use App\Domain\Guidance\Scopes\ProgramScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Follow-up rhythm after an event (10 §10.4): national (tenant_id NULL) or
 * an office's own, which takes precedence. Offsets are days.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $code
 * @property string $name_ar
 * @property TriggerEvent $trigger_event
 * @property list<int> $offsets
 * @property bool $active
 */
final class FollowUpScheduleTemplate extends Model
{
    protected $fillable = [];

    protected static function booted(): void
    {
        self::addGlobalScope(new ProgramScope);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['trigger_event' => TriggerEvent::class, 'offsets' => 'array', 'active' => 'boolean'];
    }
}
