<?php

declare(strict_types=1);

return [
    'model' => 'certificate',
    'plural' => 'Certificates',
    'templates' => ['model' => 'certificate template', 'plural' => 'Certificate templates'],
    'types' => ['shahadah' => 'Certificate of Embracing Islam', 'guidance_completion' => 'Certificate of Completion'],
    'statuses' => [
        'draft' => 'Draft', 'pending_approval' => 'Waiting for approval', 'approved' => 'Approved', 'issued' => 'Issued',
        'rejected' => 'Rejected', 'revoked' => 'Revoked', 'replaced' => 'Replaced',
    ],
    'fields' => [
        'certificate_number' => 'Certificate number', 'type' => 'Type', 'name' => 'Name', 'issued_on' => 'Issued on',
        'office' => 'Issuing center', 'status' => 'Status', 'convert' => 'Convert', 'enrollment' => 'Completed enrollment',
        'reason' => 'Reason', 'pdf' => 'PDF', 'template_code' => 'Code', 'version' => 'Version', 'html_template' => 'HTML template',
        'orientation' => 'Orientation', 'active' => 'Active', 'approved' => 'Approved',
    ],
    'help' => [
        'html_template' => 'Use {{ field }} placeholders from the certificate snapshot, e.g. {{ full_name }}, {{ certificate_number }}, {{ issued_on_hijri_ar }}, and {{ qr_code }} for the verification QR. Nothing else in the template is executed.',
    ],
    'pdf' => ['ready' => 'Ready', 'processing' => 'Being generated'],
    'actions' => [
        'request' => 'Request certificate', 'approve' => 'Approve', 'reject' => 'Reject', 'issue' => 'Issue', 'revoke' => 'Revoke',
        'regenerate' => 'Regenerate PDF', 'download' => 'Download PDF', 'new_version' => 'New version', 'approve_template' => 'Approve and activate',
    ],
    'notifications' => ['saved' => 'Saved.'],
    'verify' => [
        'title' => 'Certificate verification', 'valid' => 'Valid certificate', 'not_found' => 'This certificate could not be verified.',
    ],
    'errors' => [
        'shahadah_not_verified' => 'The shahadah must be verified first.',
        'enrollment_not_completed' => 'Choose a completed enrollment of this convert.',
        'attendance_too_low' => 'Attendance is below the program minimum.',
        'final_exam_not_passed' => 'The convert has not passed a finalized final exam of this program.',
        'already_exists' => 'A certificate of this type is already in progress or issued for this convert.',
        'convert_unavailable' => 'Choose a convert of this center.',
        'invalid_transition' => 'The certificate cannot move from :from to :to.',
        'self_approval' => 'Someone other than the requester must approve.',
        'reason_required' => 'A reason is required.',
        'no_template' => 'No approved, active certificate template for this type.',
        'template_not_editable' => 'An approved template is changed by creating a new version.',
    ],
];
