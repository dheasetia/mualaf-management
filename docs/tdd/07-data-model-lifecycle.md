# 07. Data Model — Registrasi, Syahadat, Transfer, Assignment

Notasi sama dengan [06-data-model-core.md](06-data-model-core.md).

## 7.1 Registrasi dan syahadat

```
convert_registrations
  id pk · tenant_id fk → tenants · convert_id fk → converts uq
  registration_no varchar(30)          -- dari number_sequences, format {TENANT}-REG-{YYYY}-{000001}
  registration_date date
  status enum(draft, pending_verification, verified, rejected, voided)
  registered_by fk → users · corrected_by? fk → users · verified_by? fk → users · verified_at?
  rejection_reason? · notes?          -- status juga 'returned' (dikembalikan untuk perbaikan); rejection_reason = catatannya
  uq(tenant_id, registration_no)

shahadah_records
  id pk · uuid uq · tenant_id fk → tenants · convert_id fk → converts
  convert_registration_id fk → convert_registrations
  record_type enum(first, renewal_ceremony) default 'first'
  shahadah_date date · shahadah_time? time · location
  city_id? fk → cities · latitude? · longitude?
  preacher_dai_id? fk → dais · recorded_by fk → users
  verified_by? fk → users · verified_at?
  status enum(recorded, verified, voided) · void_reason?
  notes?
  active_first_key gen uq       -- IF(record_type='first' AND status<>'voided', convert_id, NULL)

shahadah_witnesses              -- wajib (open question #2, diputuskan 23-11-2026)
  id pk · shahadah_record_id fk · convert_id fk · name · phone_encrypted enc · identity_last4? · relationship?
  created_by fk → users

convert_status_histories        -- append-only
  id pk · convert_id fk · tenant_id fk · from_status? · to_status · reason? · changed_by fk → users · created_at
```

Aturan:

- `shahadah_records.tenant_id` = kantor asal (sama dengan `converts.origin_tenant_id` untuk syahadat pertama).
- `verified_by` harus berbeda dari `recorded_by` (segregation of duties, ditegakkan service).
- Setiap syahadat dicatat bersama saksinya dalam transaksi yang sama: minimal 2 saksi (diputuskan 23-11-2026; `SHAHADAH_MIN_WITNESSES`), maksimal 5, masing-masing nama dan nomor telepon valid (disimpan ternormalisasi `+digit`, terenkripsi), tanpa telepon ganda; `identity_last4` bila diisi harus 4 digit. Nama saksi tampil di antrean verifikasi dan di scope `shahadah` akses istimewa; telepon tidak ditampilkan.
- Record `voided` hanya melalui Head of Dawah + Central approval (koreksi data), tercatat di audit; tidak pernah dihapus. Permintaan disimpan di:

```
shahadah_void_requests
  id pk · uuid uq · tenant_id fk · convert_registration_id fk · requested_by fk → users · reason
  status enum(pending, approved, rejected) · decided_by? fk → users · decision_note? · decided_at?
  pending_key gen uq                  -- satu permintaan terbuka per registrasi
```

- Verifier tidak menolak registrasi: ia mengembalikannya untuk perbaikan (12 §12.2).

## 7.2 Duplicate review

```
duplicate_review_cases
  id pk · uuid uq · tenant_id fk → tenants (kantor yang memicu)
  candidate_type enum(prospect, registration_draft, import_row) · candidate_id
  possible_convert_id fk → converts
  match_reason enum(exact_identity, fuzzy_name_dob, phone_match, manual_report)
  match_score? decimal(4,3) · match_details json
  status enum(open, in_review, resolved)
  decision? enum(same_person, different_person, inconclusive)
  reviewed_by? fk → users · notes? · resolved_at?
  idx(status, created_at)
```

Keputusan `same_person` menghentikan registrasi dan mengarahkan ke transfer. `different_person` membuka blokir registrasi dan menyimpan pasangan sebagai *known non-duplicate* agar tidak memicu lagi.

Implementasi (langkah 12): kolom tambahan `candidate_key char(64)` = SHA-256 dari fingerprint identitas kandidat, sehingga percobaan berikutnya untuk orang yang sama dikenali tanpa menyimpan nomor identitas (walk-in tidak punya `candidate_id`). Kasus dimiliki kantor yang memicu (`BelongsToTenant`) dan diputuskan di sana oleh `duplicates.review` dengan catatan wajib; `inconclusive` membuat status `in_review` (registrasi tetap ditahan), `same_person`/`different_person` menutup kasus dan tidak dapat diubah. Reviewer melihat convert lain hanya sebatas pencarian nasional (kantor, inisial) ditambah tanggal lahir dan kewarganegaraan yang dipakai skor. Audit: `duplicates.case_opened`, `duplicates.resolved`.

## 7.3 Koreksi kantor asal

```
origin_correction_requests
  id pk · uuid uq · convert_id fk
  current_origin_tenant_id fk → tenants · requested_origin_tenant_id fk → tenants
  requested_by fk → users · reason · evidence_document_id? fk → documents
  status enum(pending, approved, rejected)
  approved_by? fk → users (role Central Admin) · decision_note? · decided_at?
```

Persetujuan hanya oleh Central Admin, mengubah `origin_tenant_id` dalam transaksi, menulis audit dengan nilai lama/baru, dan memicu rekalkulasi statistik.

## 7.4 Transfer convert

```
convert_transfer_requests
  id pk · uuid uq · convert_id fk → converts
  from_tenant_id fk → tenants · to_tenant_id fk → tenants
  requested_by fk → users · reason · requested_at
  status enum(pending, under_review, approved, accepted, completed, rejected, cancelled, escalated)
  reviewed_by? fk → users · reviewed_at? · review_note?
  accepted_by? fk → users · accepted_at? · new_primary_dai_id? fk → dais
  escalated_by? · escalated_at? · escalation_note?
  completed_at?
  active_convert_key gen uq
  idx(convert_id, status) · idx(to_tenant_id, status) · idx(from_tenant_id, status)

convert_transfer_events          -- append-only
  id pk · transfer_request_id fk · actor_id fk → users · tenant_id fk
  event_type varchar(40) · from_status? · to_status? · metadata json · created_at

convert_transfer_histories       -- append-only
  id pk · convert_id fk · transfer_request_id fk uq
  from_tenant_id fk · to_tenant_id fk · approved_by fk · accepted_by fk
  previous_primary_dai_id fk → dais · new_primary_dai_id fk → dais
  transferred_at · reason · created_at
```

Implementasi transfer convert (langkah 13, `app/Domain/Transfers`): permintaan terlihat oleh kedua kantor (`TransferPartyScope`); kantor peminta hanya melihat inisial convert sampai transfer selesai. Kantor tujuan mengajukan dari hasil pencarian nasional (identitas, bukan id convert); setelah ditolak, kantor yang sama baru bisa mengajukan lagi setelah 30 hari. `current_tenant_id` hanya berubah lewat `Convert::moveToOffice()`. Eskalasi: `php artisan transfers:escalate` (terjadwal tiap jam) menandai permintaan `pending`/`under_review` yang lebih lama dari `TRANSFER_SLA_DAYS` (default 7, open question #4) sebagai `escalated`; sampai layar keputusan Central tersedia, kantor lama tetap dapat menyetujui/menolak permintaan `escalated`.

Perubahan dari v1.0: status `accepted` dan kolom `accepted_by`, `new_primary_dai_id`. Kantor tujuan mengajukan, kantor lama menyetujui, kantor tujuan menerima dengan menunjuk da'i, lalu sistem menyelesaikan transfer dalam satu transaksi (lihat [12-transactions-state-machines.md](12-transactions-state-machines.md)).

## 7.5 Transfer prospect (baru)

```
prospect_transfer_requests
  id pk · uuid uq · prospect_id fk
  from_tenant_id fk · to_tenant_id fk · requested_by fk · reason
  status enum(pending, approved, rejected, cancelled, completed)
  reviewed_by? · reviewed_at? · new_primary_dai_id? fk → dais · completed_at?
  active_prospect_key gen uq
```

Prospect transfer diajukan oleh kantor *lama* (misal prospect pindah kota) dan diterima kantor baru. Prospect lama berstatus `transferred`; record dipindahkan dengan mengubah `tenant_id` (prospect tidak punya konsep kantor asal), histori status tetap.

Implementasi (langkah 13, `Transfers\Actions\ProspectTransfers`): karena record yang sama berpindah, status **tidak** diubah menjadi `transferred` (status terminal akan membuat prospect tidak dapat ditindaklanjuti di kantor baru); perpindahan dicatat sebagai baris histori dengan status yang sama dan alasan transfer, plus audit `prospect_transfer.*`. Kantor baru menerima dengan menunjuk Primary Da'i; assignment kantor lama berakhir. `tenant_id` hanya bisa berubah lewat `Prospect::moveToOffice()` (`BelongsToTenant` menolak perubahan lain). Satu permintaan `pending` per prospect (`active_prospect_key`).

## 7.6 Assignment da'i

```
dai_assignments                  -- pengganti dai_assignments_to_people
  id pk · tenant_id fk → tenants · dai_id fk → dais
  subject_type enum(prospect, convert) · subject_id
  assignment_type enum(primary, secondary, teacher_support, temporary)
  is_primary bool · assigned_at · ended_at? · end_reason?
  assigned_by fk → users
  active_primary_key gen uq     -- IF(is_primary AND ended_at IS NULL, CONCAT(subject_type,':',subject_id), NULL)
  idx(dai_id, ended_at) · idx(subject_type, subject_id)
```

Tabel dan model `DaiAssignment` dibuat pada langkah 7; `DaiAssignmentService` (langkah 8) adalah satu-satunya penulisnya. Subjek (prospect, nanti convert) mengimplementasikan `App\Domain\Dai\Contracts\AssignableSubject`, sehingga modul Da'i tidak meng-import model subjek; modul lain memanggil service dengan id da'i, bukan model `Dai`. Menugaskan ulang da'i yang sama tidak mengubah apa pun.

`DaiAssignmentService::assignPrimary(subject, dai, reason)`:

1. Lock subjek.
2. Akhiri assignment primary aktif (`ended_at = now`).
3. Insert assignment baru.
4. Update `primary_dai_id` pada subjek.
5. Audit `dai.primary_assigned`.

Da'i harus berasal dari tenant yang sama dengan subjek (prospect `tenant_id`, convert `current_tenant_id`) dan berstatus `active`.

## 7.7 Follow-up da'i

```
dai_follow_ups
  id pk · tenant_id fk · dai_id fk → dais
  subject_type enum(prospect, convert) · subject_id
  follow_up_type enum(visit, call, message, discussion, dialogue, class, meeting, other)
  contact_method enum(in_person, phone, whatsapp, video, email, other)
  occurred_at · duration_minutes? · location?
  summary text · questions_raised? text
  material_id? fk → dawah_materials · outcome?
  next_action? · next_follow_up_at?
  confidentiality_level enum(normal, confidential) default 'normal'
  created_by fk → users
  idx(dai_id, next_follow_up_at) · idx(subject_type, subject_id, occurred_at)
```

Aturan follow-up (langkah 8): dicatat oleh record da'i milik pengguna (pengguna tanpa record da'i tidak dapat mencatat); pemegang `*_own` hanya untuk subjek yang ditugaskan kepadanya. Untuk prospect, `RecordProspectFollowUp` memperbarui `first_contact_at` (paling awal), `last_contact_at` dan `next_follow_up_at` (dari follow-up terbaru), dan memindahkan prospect `new` ke `contacted`. Follow-up `confidential` hanya terlihat oleh penulisnya dan pemegang `followups.view_confidential`. `material_id` tanpa FK sampai `dawah_materials` ada (langkah 20).

Setelah insert, service memperbarui `last_contact_at`/`next_follow_up_at` pada subjek. Follow-up `confidential` hanya terbaca oleh penulis, Primary Da'i, dan pemegang `followups.view_confidential`.

## 7.8 Follow-up tasks

```
follow_up_tasks
  id pk · tenant_id fk · related_type · related_id
  title · description? · assigned_to fk → users
  priority enum(low, normal, high, urgent) · due_at
  status enum(open, in_progress, done, cancelled) · completed_at?
  created_by fk → users
  idx(assigned_to, status, due_at)
```
