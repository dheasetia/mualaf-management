<?php

declare(strict_types=1);

return [
    'navigation_group' => 'International follow-up',
    'consents' => ['model' => 'consent', 'plural' => 'Consents'],
    'home_contacts' => ['model' => 'home-country contact', 'plural' => 'Home-country contacts'],
    'home_locations' => ['model' => 'home location', 'plural' => 'Home locations'],
    'organizations' => ['model' => 'organization abroad', 'plural' => 'Islamic organizations abroad'],
    'referrals' => ['model' => 'referral', 'plural' => 'International referrals'],
    'checklists' => ['model' => 'pre-departure checklist', 'plural' => 'Pre-departure checklists'],
    'consent_types' => [
        'international_sharing' => 'Share with follow-up abroad', 'contact_employer' => 'Contact employer', 'contact_family' => 'Contact family',
        'photo_use' => 'Use of photos', 'pilgrimage_participation' => 'Umrah/Hajj participation',
    ],
    'consent_methods' => ['written' => 'Written', 'verbal_witnessed' => 'Verbal, witnessed', 'digital' => 'Digital'],
    'relationships' => [
        'spouse' => 'Spouse', 'parent' => 'Parent', 'sibling' => 'Sibling', 'child' => 'Child', 'relative' => 'Relative', 'friend' => 'Friend',
        'muslim_contact' => 'Muslim contact', 'local_dai' => "Local da'i", 'other' => 'Other',
    ],
    'organization_types' => ['mosque' => 'Mosque', 'islamic_center' => 'Islamic center', 'dawah_org' => 'Dawah organization', 'school' => 'School', 'individual_dai' => "Individual da'i"],
    'organization_statuses' => ['unverified' => 'Unverified', 'verified' => 'Verified', 'inactive' => 'Inactive'],
    'shared_fields' => [
        'name' => 'Name', 'islamic_name' => 'Islamic name', 'gender' => 'Gender', 'shahadah_date' => 'Shahadah date', 'guidance_status' => 'Guidance status',
        'languages' => 'Languages', 'contact' => 'Contact (who agreed)', 'home_city' => 'Home city', 'support_needed' => 'Support needed',
    ],
    'referral_statuses' => [
        'draft' => 'Draft', 'pending_consent' => 'Waiting for consent', 'ready' => 'Ready to send', 'sent' => 'Sent',
        'acknowledged' => 'Acknowledged', 'active' => 'Active', 'closed' => 'Closed', 'declined' => 'Declined',
    ],
    'checklist_statuses' => ['open' => 'Open', 'completed' => 'Completed', 'cancelled' => 'Cancelled'],
    'checklist_items' => [
        'contact_confirmed' => 'Contact confirmed', 'address_confirmed' => 'Address confirmed', 'mosque_identified' => 'Mosque identified',
        'local_dai_identified' => "Local da'i identified", 'consent_obtained' => 'Consent obtained', 'referral_sent' => 'Referral sent',
        'acknowledgment_received' => 'Acknowledgment received', 'follow_up_scheduled' => 'Follow-up scheduled',
    ],
    'item_statuses' => ['pending' => 'Pending', 'done' => 'Done', 'not_applicable' => 'Not applicable'],
    'fields' => [
        'consent_type' => 'Type', 'fields' => 'Information covered', 'destination_country' => 'Destination country', 'given_at' => 'Given on',
        'method' => 'Method', 'witnessed_by' => 'Witness', 'expires_at' => 'Expires', 'status' => 'Status', 'reason' => 'Reason',
        'country' => 'Country', 'contact_name' => 'Name', 'relationship' => 'Relationship', 'phone' => 'Phone', 'whatsapp' => 'WhatsApp', 'email' => 'Email',
        'address' => 'Address', 'city' => 'City', 'province_state' => 'Province/state', 'district' => 'District', 'village' => 'Village',
        'is_muslim' => 'Muslim', 'is_primary' => 'Primary', 'permission_to_contact' => 'Agreed to be contacted', 'disclosure_scope' => 'Knows about the conversion',
        'notes' => 'Notes', 'convert' => 'Convert', 'destination_city' => 'Destination city', 'organization' => 'Organization abroad',
        'external_contact_name' => 'Contact abroad', 'external_contact_phone' => 'Contact phone', 'referral_reason' => 'Reason for referral',
        'information_scope' => 'Information to share', 'sent_at' => 'Sent', 'packet' => 'Shared information', 'organization_name' => 'Name',
        'organization_type' => 'Type', 'contact_person' => 'Contact person', 'website' => 'Website', 'verification_status' => 'Verification',
        'expected_departure_on' => 'Expected departure', 'item' => 'Item', 'latitude' => 'Latitude', 'longitude' => 'Longitude', 'google_maps_url' => 'Google Maps link',
    ],
    'actions' => [
        'record_consent' => 'Record consent', 'withdraw' => 'Withdraw', 'add' => 'Add', 'create_referral' => 'New referral', 'check_consent' => 'Check consent',
        'send' => 'Send', 'acknowledge' => 'Acknowledged', 'activate' => 'Active', 'decline' => 'Declined', 'close' => 'Close', 'packet' => 'Shared information',
        'open_checklist' => 'Open checklist', 'done' => 'Done', 'not_applicable' => 'Not applicable', 'reset' => 'Pending', 'complete' => 'Complete',
        'cancel' => 'Cancel', 'verify' => 'Mark verified', 'deactivate' => 'Mark inactive', 'open' => 'Open',
    ],
    'notifications' => ['saved' => 'Saved.'],
    'errors' => [
        'invalid_transition' => 'Cannot move from :from to :to.',
        'reason_required' => 'A reason is required.',
        'consent_missing' => 'No active consent to share this information abroad; the referral waits for consent.',
        'scope_required' => 'Choose the information covered.',
        'already_withdrawn' => 'This consent is already withdrawn.',
        'witness_required' => 'A verbal consent needs a witness other than you.',
        'checklist_open' => 'This convert already has an open checklist.',
        'items_pending' => 'Mark every item done or not applicable first.',
        'organization_unavailable' => 'Choose an active organization from the directory.',
    ],
];
