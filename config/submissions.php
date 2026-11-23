<?php

declare(strict_types=1);

return [
    /*
    | Days past due_at after which an open submission is escalated once to the
    | office's approvers (submissions.approve). The daily reminder to the
    | person responsible starts on the first day past due.
    */
    'escalate_after_days' => (int) env('SUBMISSION_ESCALATION_DAYS', 3),
];
