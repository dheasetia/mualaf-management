<?php

declare(strict_types=1);

return [
    'convert_model' => 'convert transfer',
    'convert_plural' => 'Convert transfers',
    'prospect_model' => 'prospect transfer',
    'prospect_plural' => 'Prospect transfers',
    'fields' => [
        'direction' => 'Direction',
        'convert' => 'Convert',
        'prospect' => 'Prospect',
        'from' => 'From',
        'to' => 'To',
        'reason' => 'Reason',
        'note' => 'Note',
        'status' => 'Status',
        'requested_at' => 'Requested',
        'requested_by' => 'Requested by',
        'new_primary_dai' => "New primary da'i",
        'destination' => 'Destination center',
        'transferred_at' => 'Transferred',
    ],
    'directions' => [
        'incoming' => 'To us',
        'outgoing' => 'From us',
    ],
    'actions' => [
        'request' => 'Request transfer',
        'start_review' => 'Take into review',
        'approve' => 'Approve',
        'reject' => 'Reject',
        'accept' => "Accept and assign da'i",
        'cancel' => 'Cancel',
        'transfer_prospect' => 'Transfer to another center',
    ],
    'notifications' => [
        'saved' => 'Saved.',
        'requested' => 'Transfer requested. The current center will review it.',
    ],
    'statuses' => [
        'pending' => 'Pending', 'under_review' => 'Under review', 'approved' => 'Approved', 'accepted' => 'Accepted',
        'completed' => 'Completed', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled', 'escalated' => 'Escalated to Central',
    ],
    'errors' => [
        'not_found' => 'No convert is registered with this identity.',
        'same_office' => 'This convert is already in your center.',
        'already_active' => 'A transfer for this person is already in progress.',
        'recently_rejected' => 'The current center rejected your request recently. Ask again after :days days or contact Central.',
        'invalid_transition' => 'The transfer cannot move from :from to :to.',
        'note_required' => 'A reason is required.',
        'not_your_side' => 'This step belongs to the other center.',
        'office_changed' => 'The convert is no longer in the center that approved the transfer.',
        'invalid_destination' => 'Choose another active center.',
        'closed_prospect' => 'This prospect is closed and cannot be transferred.',
    ],
];
