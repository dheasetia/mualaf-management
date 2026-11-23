<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\FollowUp\Enums\TriggerEvent;
use App\Domain\FollowUp\Models\FollowUpScheduleTemplate;
use Illuminate\Database\Seeder;

/**
 * National follow-up rhythms (10 §10.4); offices may add their own.
 */
final class FollowUpScheduleTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            'after_shahadah' => ['متابعة بعد إشهار الإسلام', TriggerEvent::Shahadah, [7, 30, 90, 180, 365]],
            'after_guidance' => ['متابعة بعد إتمام التأهيل', TriggerEvent::GuidanceCompleted, [30, 90, 180]],
            'after_return_home' => ['متابعة بعد العودة إلى البلد', TriggerEvent::ReturnedHome, [7, 30, 90, 180, 365]],
            'after_transfer' => ['متابعة بعد النقل', TriggerEvent::TransferCompleted, [7, 30]],
        ];

        foreach ($templates as $code => [$name, $trigger, $offsets]) {
            if (FollowUpScheduleTemplate::query()->whereNull('tenant_id')->where('code', $code)->exists()) {
                continue;
            }

            $template = new FollowUpScheduleTemplate;
            $template->code = $code;
            $template->name_ar = $name;
            $template->trigger_event = $trigger;
            $template->offsets = $offsets;
            $template->active = true;
            $template->save();
        }
    }
}
