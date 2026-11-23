<?php

declare(strict_types=1);

return [
    'open' => 'Open',
    'shahadah_recorded' => ['title' => 'Shahadah awaiting verification', 'body' => 'A new shahadah registration needs verification.'],
    'shahadah_verified' => ['title' => 'Shahadah verified', 'body' => 'A shahadah was verified; its certificate can be requested.'],
    'enrollment_completed' => ['title' => 'Guidance program completed', 'body' => 'A convert completed a guidance program; a certificate can be requested.'],
    'transfer_requested' => ['title' => 'New transfer request', 'body' => 'Another center asked to take over a convert of this center.'],
    'transfer_approved' => ['title' => 'Transfer approved', 'body' => 'A transfer you requested was approved; accept it to complete.'],
    'transfer_completed_out' => ['title' => 'Transfer completed', 'body' => 'A convert has moved to another center.'],
    'transfer_completed_in' => ['title' => 'Transfer completed', 'body' => 'A convert has joined this center.'],
    'transfer_new_convert' => ['title' => 'New convert assigned', 'body' => 'A transferred convert was assigned to you.'],
    'consent_withdrawn' => ['title' => 'Consent withdrawn', 'body' => 'A convert withdrew a consent; :count related referral(s) were closed.'],
    'follow_ups_overdue' => ['title' => 'Overdue follow-ups', 'body' => 'You have :count overdue follow-up(s).'],
    'follow_ups_escalated' => ['title' => 'Follow-ups overdue for over a week', 'body' => ':count follow-up(s) in this center are more than 7 days overdue.'],
    'shahadah_returned' => ['title' => 'Registration returned for correction', 'body' => 'A shahadah registration was returned to you for correction.'],
    'shahadah_void_requested' => ['title' => 'Shahadah void requested', 'body' => 'A center asked to void a shahadah registration; it is waiting for your decision.'],
    'shahadah_void_approved' => ['title' => 'Shahadah voided', 'body' => 'Your request to void a shahadah registration was approved.'],
    'shahadah_void_rejected' => ['title' => 'Void request rejected', 'body' => 'Your request to void a shahadah registration was rejected.'],
    'privileged_requested' => ['title' => 'Privileged access requested', 'body' => 'A privileged access request is waiting for your decision.'],
    'privileged_approved' => ['title' => 'Privileged access approved', 'body' => 'Your privileged access request was approved; the access has started.'],
    'privileged_rejected' => ['title' => 'Privileged access rejected', 'body' => 'Your privileged access request was rejected.'],
];
