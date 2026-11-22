<?php

declare(strict_types=1);

return [
    'model' => 'document',
    'plural' => 'Documents',
    'letters' => ['model' => 'letter', 'plural' => 'Letters'],
    'templates' => ['model' => 'letter template', 'plural' => 'Letter templates'],
    'document_types' => [
        'iqamah_copy' => 'Iqamah copy', 'passport_copy' => 'Passport copy', 'photo' => 'Photo',
        'medical_letter' => 'Medical letter', 'receipt' => 'Receipt', 'dawah_material' => 'Dawah material', 'other' => 'Other',
    ],
    'letter_types' => [
        'shahadah_certificate' => 'Shahadah certificate', 'new_convert_data_letter' => 'New convert data letter',
        'khitan_referral_letter' => 'Circumcision referral letter', 'religion_update_letter' => 'Religion update support letter',
        'guidance_certificate' => 'Guidance certificate', 'other_letter' => 'Other letter',
    ],
    'verification_statuses' => ['pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected'],
    'generated_statuses' => ['draft' => 'Draft', 'pending_approval' => 'Waiting for approval', 'issued' => 'Issued', 'cancelled' => 'Cancelled'],
    'fields' => [
        'type' => 'Type', 'file' => 'File', 'filename' => 'File name', 'version' => 'Version', 'status' => 'Status',
        'uploaded_at' => 'Uploaded', 'reason' => 'Reason', 'number' => 'Number', 'addressee' => 'Addressed to',
        'body' => 'Additional text', 'issued_on' => 'Issued on', 'pdf' => 'PDF', 'template_code' => 'Code',
        'name' => 'Name', 'description' => 'Description', 'html_template' => 'HTML template', 'orientation' => 'Orientation',
        'active' => 'Active', 'approved' => 'Approved',
    ],
    'help' => [
        'file' => 'PDF, JPEG, PNG or WebP, up to 10 MB. Photo location data is removed.',
        'html_template' => 'Use {{ field }} placeholders from the letter snapshot, e.g. {{ full_name }}, {{ document_number }}, {{ addressee }}, {{ body }}. Nothing else in the template is executed.',
    ],
    'pdf' => ['ready' => 'Ready', 'processing' => 'Being generated'],
    'actions' => [
        'upload' => 'Upload', 'new_version' => 'Upload new version', 'verify' => 'Verify', 'reject' => 'Reject',
        'download' => 'Download', 'generate' => 'Generate letter', 'cancel' => 'Cancel letter',
        'new_template_version' => 'New version', 'approve_template' => 'Approve and activate',
    ],
    'notifications' => ['saved' => 'Saved.'],
    'errors' => [
        'file_type' => 'Only PDF, JPEG, PNG or WebP files are accepted.',
        'file_too_large' => 'The file is larger than :mb MB.',
        'already_decided' => 'This document has already been verified or rejected.',
        'own_upload' => 'Someone other than the uploader must verify.',
        'reason_required' => 'A reason is required.',
        'shahadah_not_verified' => 'The shahadah must be verified first.',
        'letter_not_for_convert' => 'Certificates are issued from the certificates screen.',
        'khitan_male_only' => 'A circumcision referral is for male converts only.',
        'no_template' => 'No approved, active template for this letter type.',
        'not_issued' => 'Only an issued letter can be cancelled.',
        'self_approval' => 'Someone other than the author must approve.',
        'template_not_editable' => 'An approved template is changed by creating a new version.',
    ],
];
