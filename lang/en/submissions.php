<?php

declare(strict_types=1);

return [
    'navigation_group' => 'Administration',
    'model' => 'submission',
    'plural' => 'Submissions',
    'documents' => ['plural' => 'Documents'],
    'statuses' => [
        'draft' => 'Draft', 'submitted' => 'Submitted', 'under_verification' => 'Under verification', 'needs_correction' => 'Needs correction',
        'approved' => 'Approved', 'processing' => 'Processing', 'completed' => 'Completed', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled',
    ],
    'document_checks' => ['pending' => 'Not checked', 'verified' => 'Verified', 'rejected' => 'Rejected'],
    'decisions' => ['approved' => 'Approved', 'rejected' => 'Rejected', 'returned' => 'Returned'],
    'assignment_types' => ['verifier' => 'Verifier', 'processor' => 'Processor', 'approver' => 'Approver'],
    'fields' => [
        'reference_no' => 'Reference', 'convert' => 'Convert', 'type' => 'Type', 'status' => 'Status', 'notes' => 'Notes',
        'submitted_at' => 'Submitted', 'due_at' => 'Due', 'assigned_to' => 'Assigned to', 'correction_note' => 'Correction requested',
        'requirement' => 'Requirement', 'file' => 'File', 'check' => 'Check', 'note' => 'Note', 'reason' => 'Reason',
        'addressee' => 'Letter addressed to', 'user' => 'User', 'assignment_type' => 'Role', 'events' => 'History',
        'event' => 'Event', 'when' => 'When', 'approvals' => 'Approvals', 'stage' => 'Stage', 'decision' => 'Decision', 'letter' => 'Issued letter',
    ],
    'events' => [
        'created' => 'Created', 'document_attached' => 'Document attached', 'document_verified' => 'Document verified',
        'document_rejected' => 'Document rejected', 'status_changed' => 'Status changed', 'stage_approved' => 'Stage approved', 'assigned' => 'Assigned',
    ],
    'actions' => [
        'create' => 'New submission', 'attach' => 'Attach document', 'submit' => 'Submit', 'start_verification' => 'Start verification',
        'verify_document' => 'Verified', 'reject_document' => 'Reject document', 'request_correction' => 'Request correction',
        'approve' => 'Approve', 'reject' => 'Reject', 'start_processing' => 'Start processing', 'complete' => 'Complete and issue letter',
        'cancel' => 'Cancel', 'assign' => 'Assign', 'download' => 'Download',
    ],
    'notifications' => ['saved' => 'Saved.'],
    'errors' => [
        'invalid_transition' => 'The submission cannot move from :from to :to.',
        'reason_required' => 'A reason is required.',
        'convert_unavailable' => 'Choose a convert you can see in this center.',
        'type_unavailable' => 'Choose an active submission type.',
        'unknown_requirement' => 'This document is not part of the submission type.',
        'documents_locked' => 'Documents can be changed only while the submission is a draft or returned for correction.',
        'missing_documents' => 'Attach the required documents first: :documents.',
        'documents_not_verified' => 'Every attached document must be verified first.',
        'approver_repeated' => 'Each approval stage needs a different person, other than the creator.',
        'assignee_lacks_permission' => 'This user does not hold the permission for that role here.',
    ],
];
