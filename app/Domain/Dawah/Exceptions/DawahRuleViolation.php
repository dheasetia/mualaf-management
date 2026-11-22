<?php

declare(strict_types=1);

namespace App\Domain\Dawah\Exceptions;

use App\Support\Exceptions\RuleViolation;

final class DawahRuleViolation extends RuleViolation
{
    public static function invalidTransition(string $from, string $to): self
    {
        return new self(__('dawah.errors.invalid_transition', ['from' => $from, 'to' => $to]), 'status');
    }

    public static function notEditable(): self
    {
        return new self(__('dawah.errors.not_editable'), 'status');
    }

    public static function reasonRequired(): self
    {
        return new self(__('dawah.errors.reason_required'), 'reason');
    }

    public static function noDaiProfile(): self
    {
        return new self(__('dawah.errors.no_dai_profile'), 'responsible_dai_id');
    }

    public static function daiUnavailable(): self
    {
        return new self(__('dawah.errors.dai_unavailable'), 'responsible_dai_id');
    }

    public static function activityTypeUnavailable(): self
    {
        return new self(__('dawah.errors.activity_type_unavailable'), 'activity_type_id');
    }

    public static function dateInPast(): self
    {
        return new self(__('dawah.errors.date_in_past'), 'planned_date');
    }

    public static function endBeforeStart(): self
    {
        return new self(__('dawah.errors.end_before_start'), 'end_time');
    }

    public static function selfApproval(): self
    {
        return new self(__('dawah.errors.self_approval'), 'status');
    }

    public static function tooEarlyToStart(): self
    {
        return new self(__('dawah.errors.too_early_to_start'), 'status');
    }

    public static function participantsClosed(): self
    {
        return new self(__('dawah.errors.participants_closed'), 'participant_type');
    }

    public static function participantUnavailable(): self
    {
        return new self(__('dawah.errors.participant_unavailable'), 'participant_id');
    }

    public static function participantNameRequired(): self
    {
        return new self(__('dawah.errors.participant_name_required'), 'participant_name');
    }

    public static function alreadyParticipant(): self
    {
        return new self(__('dawah.errors.already_participant'), 'participant_id');
    }

    public static function reportMissing(): self
    {
        return new self(__('dawah.errors.report_missing'), 'summary');
    }

    public static function futureActivity(): self
    {
        return new self(__('dawah.errors.future_activity'), 'activity_date');
    }

    public static function relatedUnavailable(): self
    {
        return new self(__('dawah.errors.related_unavailable'), 'related_convert_id');
    }

    public static function ownActivity(): self
    {
        return new self(__('dawah.errors.own_activity'), 'status');
    }

    public static function budgetNotApproved(): self
    {
        return new self(__('dawah.errors.budget_not_approved'), 'status');
    }

    public static function budgetClosed(): self
    {
        return new self(__('dawah.errors.budget_closed'), 'status');
    }

    public static function invalidAmount(): self
    {
        return new self(__('dawah.errors.invalid_amount'), 'amount');
    }

    public static function varianceNoteRequired(): self
    {
        return new self(__('dawah.errors.variance_note_required'), 'notes');
    }

    public static function categoryUnavailable(): self
    {
        return new self(__('dawah.errors.category_unavailable'), 'budget_category_id');
    }

    public static function materialNotDistributable(): self
    {
        return new self(__('dawah.errors.material_not_distributable'), 'material_id');
    }

    public static function insufficientStock(): self
    {
        return new self(__('dawah.errors.insufficient_stock'), 'quantity');
    }

    public static function languageUnavailable(): self
    {
        return new self(__('dawah.errors.language_unavailable'), 'language_id');
    }

    public static function materialSourceRequired(): self
    {
        return new self(__('dawah.errors.material_source_required'), 'external_url');
    }

    public static function selfReview(): self
    {
        return new self(__('dawah.errors.self_review'), 'status');
    }
}
