# 10. Data Model — Submission, Umrah/Haji, Follow-up Jangka Panjang dan Internasional

Notasi sama dengan [06-data-model-core.md](06-data-model-core.md).

## 10.1 Submission administratif

```
submission_types
  id pk · code uq · name_ar · name_en? · description?
  required_documents json        -- [{code, name_ar, required, accepted_mime}]
  workflow_config json           -- tahap approval, role per tahap, SLA hari
  generates_document_template_code?
  active bool

submissions
  id pk · uuid uq · tenant_id fk · convert_id fk · submission_type_id fk
  reference_no varchar(30)       -- {TENANT}-SUB-{YYYY}-{000001}
  status enum(draft, submitted, under_verification, needs_correction, approved,
              processing, completed, rejected, cancelled)
  assigned_to? fk → users
  submitted_at? · approved_at? · completed_at? · due_at?
  created_by fk → users
  uq(tenant_id, reference_no) · idx(tenant_id, status, created_at)

submission_documents
  id pk · submission_id fk · document_id fk · requirement_code
  verification_status enum(pending, verified, rejected) · verified_by? · verified_at? · rejection_note?

submission_events                -- append-only
  id pk · submission_id fk · actor_id fk · event_type · from_status? · to_status?
  reason? · metadata json · created_at

submission_approvals
  id pk · submission_id fk · approval_stage tinyint · approver_id fk → users
  decision enum(approved, rejected, returned) · reason? · decided_at
  uq(submission_id, approval_stage)

submission_assignments
  id pk · submission_id fk · user_id fk · assignment_type enum(verifier, processor, approver)
  assigned_at · completed_at?
```

Jenis seed: pengajuan data mualaf baru, surat khitan, surat pembaruan data agama, surat lain. Submission khitan menyimpan dokumen medis hanya sebagai lampiran dengan akses `submissions.view`.

## 10.2 Umrah dan haji

```
pilgrimage_programs
  id pk · uuid uq · tenant_id fk · program_type enum(umrah, hajj)
  name · season_year smallint · starts_on · ends_on · registration_deadline
  capacity int · eligibility_rules json   -- misal: minimal bulan sejak syahadat, program pembinaan selesai
  status enum(draft, open, closed, in_progress, completed, cancelled)

pilgrimage_requirements
  id pk · program_id fk · code · description · required bool

pilgrimage_registrations
  id pk · tenant_id fk · program_id fk · convert_id fk
  status enum(applied, eligibility_checked, eligible, ineligible, approved, waitlisted,
              confirmed, departed, completed, withdrawn)
  eligibility_checked_by? · eligibility_checked_at? · eligibility_notes?
  approved_by? · approved_at? · consent_record_id? fk → consent_records
  uq(program_id, convert_id)

pilgrimage_registration_requirements
  id pk · registration_id fk · requirement_id fk · document_id? fk
  status enum(pending, fulfilled, waived) · verified_by? · verified_at?

pilgrimage_groups
  id pk · program_id fk · group_name · leader_user_id fk → users · capacity int

pilgrimage_group_members
  group_id fk · pilgrimage_registration_id fk uq
  pk(group_id, pilgrimage_registration_id)

pilgrimage_events                -- append-only
  id pk · program_id fk · registration_id? fk · actor_id fk · event_type · metadata json · created_at
```

Kuota: approval memeriksa jumlah `approved/confirmed` < `capacity` dengan lock pada `pilgrimage_programs`. Transportasi memakai `activity_transport_requests` yang ditautkan ke program melalui kegiatan.

## 10.3 Status follow-up jangka panjang

```
convert_follow_up_statuses       -- append-only; baris terbaru = status terkini
  id pk · convert_id fk · tenant_id fk · primary_dai_id fk → dais
  status enum(active_guidance, stable, needs_follow_up, needs_community_connection,
              temporarily_unreachable, lost_contact, returned_home, followed_abroad)
  last_contact_at? · last_contact_method?
  last_known_country_id? fk → countries · last_known_city?
  islamic_activity_status enum(regular, irregular, unknown)?
  community_connection_status enum(connected, partially, not_connected, unknown)?
  next_follow_up_at? · follow_up_priority enum(low, normal, high)
  notes? · recorded_by fk → users · created_at
  idx(convert_id, created_at)
```

`converts.current_follow_up_status_id` (didefinisikan di [06-data-model-core.md](06-data-model-core.md)) menunjuk baris terbaru agar daftar convert tidak perlu subquery. Service yang menulis status baru memperbaruinya dalam transaksi yang sama.

Aturan PRD §31: sistem tidak pernah menetapkan status murtad atau mengubah `converts.status` berdasarkan ketidakhadiran atau tidak dapat dihubungi. Job otomatis hanya boleh menyarankan `needs_follow_up` / `temporarily_unreachable` sebagai tugas untuk da'i, bukan menulis status.

## 10.4 Jadwal follow-up

```
follow_up_schedule_templates     -- baru
  id pk · tenant_id? fk (NULL = nasional) · code · name_ar
  trigger_event enum(shahadah, guidance_completed, returned_home, transfer_completed)
  offsets json                   -- contoh: [7, 30, 90, 180, 365] (hari)
  active bool

convert_follow_up_schedules
  id pk · convert_id fk · tenant_id fk · dai_id fk → dais
  template_id? fk · scheduled_at · follow_up_type
  priority enum(low, normal, high) · reason?
  status enum(scheduled, completed, missed, cancelled, rescheduled)
  completed_at? · outcome? · long_term_follow_up_id? fk
  idx(dai_id, scheduled_at, status)
```

Event `ShahadahVerified`, `EnrollmentCompleted`, `ConvertReturnedHome` membuat jadwal dari template. Jadwal terlambat = `status = scheduled AND scheduled_at < now()`; tampil di dashboard da'i dan Head of Dawah.

## 10.5 Catatan follow-up jangka panjang

```
convert_long_term_follow_ups
  id pk · tenant_id fk · convert_id fk · dai_id fk → dais
  follow_up_at · contact_method · contacted_person_type enum(convert, family, employer, friend, local_dai, other)
  contact_result enum(reached, not_reached, wrong_number, refused)
  current_country_id? fk · current_city?
  current_activity_status? · mosque_or_center_name? · local_dai_name?
  learning_status? · support_needed? text · next_action? text · next_follow_up_at?
  confidentiality_level enum(normal, confidential) · notes?
  idx(convert_id, follow_up_at)
```

## 10.6 Negara asal

```
convert_home_country_contacts
  id pk · convert_id fk · country_id fk
  contact_name · relationship enum(spouse, parent, sibling, child, relative, friend, muslim_contact, local_dai, other)
  phone_encrypted? enc · whatsapp_encrypted? enc · email_encrypted? enc
  address? · city? · preferred_language_id? fk
  is_muslim? bool · is_primary bool
  permission_to_contact bool default false
  disclosure_scope enum(none, general_support, knows_conversion) default 'none'
  notes?

convert_home_locations
  id pk · convert_id fk · country_id fk
  province_state? · city? · district? · village? · address?
  latitude? · longitude? · google_maps_url? · is_primary bool · notes?
```

## 10.7 Organisasi Islam luar negeri dan referral

```
external_islamic_organizations
  id pk · uuid uq · country_id fk · city
  organization_name · organization_type enum(mosque, islamic_center, dawah_org, school, individual_dai)
  contact_person? · phone? · whatsapp? · email? · website? · address?
  latitude? · longitude?
  verification_status enum(unverified, verified, inactive) · verified_by? · verified_at? · notes?
  -- direktori nasional, dikelola Central (international.organizations.manage)

international_follow_up_referrals
  id pk · uuid uq · convert_id fk
  origin_tenant_id fk · referring_tenant_id fk · primary_dai_id fk → dais
  destination_country_id fk · destination_city?
  external_organization_id? fk · external_contact_name? · external_contact_phone?
  referral_reason · information_scope json     -- daftar field yang boleh dibagikan
  consent_record_id fk → consent_records
  status enum(draft, pending_consent, ready, sent, acknowledged, active, closed, declined)
  sent_at? · sent_by? · acknowledged_at? · closed_at? · close_reason?
  created_by fk → users
```

Checklist sebelum kepulangan (PRD §33):

```
pre_departure_checklists
  id pk · uuid uq · tenant_id fk · convert_id fk · primary_dai_id fk → dais
  expected_departure_on? date · destination_country_id fk → countries
  status enum(open, completed, cancelled) · completed_at?
  referral_id? fk → international_follow_up_referrals
  created_by fk → users

pre_departure_checklist_items
  id pk · checklist_id fk
  item_code enum(contact_confirmed, address_confirmed, mosque_identified, local_dai_identified,
                 consent_obtained, referral_sent, acknowledgment_received, follow_up_scheduled)
  status enum(pending, done, not_applicable) · done_by? fk → users · done_at? · notes?
  uq(checklist_id, item_code)
```

Checklist dibuat oleh event `ConvertReturnedHome` atau manual oleh da'i. Status referral `sent` memerlukan consent aktif dengan scope `international_sharing` yang mencakup `information_scope`.

## 10.8 Consent (baru)

```
consent_records
  id pk · uuid uq · tenant_id fk · convert_id fk
  consent_type enum(international_sharing, contact_employer, contact_family, photo_use, pilgrimage_participation)
  scope json · given_at · method enum(written, verbal_witnessed, digital)
  evidence_document_id? fk → documents · witnessed_by? fk → users
  expires_at? · withdrawn_at? · withdrawn_reason?
  recorded_by fk → users
  idx(convert_id, consent_type)
```

Consent ditarik → referral yang bergantung berubah `closed` dengan alasan `consent_withdrawn`, dan notifikasi ke Primary Da'i.
