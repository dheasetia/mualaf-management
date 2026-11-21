# 08. Data Model — Aktivitas Da'i, Rencana Dakwah, Budget, Materi, Transportasi

Notasi sama dengan [06-data-model-core.md](06-data-model-core.md).

## 8.1 Aktivitas harian da'i

```
dai_daily_activities
  id pk · tenant_id fk · dai_id fk → dais
  activity_date date · activity_type_id fk → dai_activity_types
  title · description?
  started_at? · ended_at? · duration_minutes?
  related_prospect_id? fk → prospects · related_convert_id? fk → converts
  related_activity_plan_id? fk → dawah_activity_plans
  output_quantity? decimal(10,2) · output_unit? varchar(30)   -- misal: halaman, menit video, artikel
  status enum(draft, submitted, verified, rejected)
  verified_by? fk → users · verified_at? · rejection_note?
  idx(dai_id, activity_date) · idx(tenant_id, activity_date, status)
```

Aktivitas dengan `requires_verification` (dari jenis aktivitas) diverifikasi Head of Dawah. Da'i hanya dapat mengubah aktivitas berstatus `draft`/`rejected`.

## 8.2 Rencana kegiatan dakwah

```
dawah_activity_plans
  id pk · uuid uq · tenant_id fk
  plan_number varchar(30)       -- {TENANT}-PLAN-{YYYY}-{0001}
  title · activity_type_id fk → dai_activity_types
  objective text · description? text · target_audience? · expected_participants? int
  proposed_by_dai_id fk → dais · responsible_dai_id fk → dais
  supervisor_user_id? fk → users
  planned_date date · start_time? · end_time?
  city_id? fk → cities · location? · latitude? · longitude? · google_maps_url?
  estimated_budget decimal(14,2) default 0   -- dihitung dari dawah_activity_budgets
  approved_budget? decimal(14,2) · actual_budget? decimal(14,2)
  transport_required bool · material_required bool · equipment_required? text
  status enum(draft, submitted, supervisor_review, needs_revision, approved, rejected,
              scheduled, in_progress, completed, report_submitted, verified, cancelled)
  submitted_at? · approved_by? fk → users · approved_at? · rejection_reason?
  uq(tenant_id, plan_number)
  idx(tenant_id, planned_date, status)
```

Perubahan dari v1.0: kolom `approval_status` dihapus (digabung ke `status` agar satu state machine); cabang `needs_revision`, `rejected`, `cancelled` ditambahkan; `supervisor_id` dirujuk ke `users`.

## 8.3 Budget

```
dawah_activity_budgets
  id pk · activity_plan_id fk → dawah_activity_plans · budget_category_id fk → budget_categories
  description · quantity decimal(10,2) · unit_cost decimal(14,2)
  estimated_amount decimal(14,2)       -- quantity × unit_cost, dihitung service
  approved_amount? decimal(14,2) · actual_amount? decimal(14,2)
  variance_amount gen decimal(14,2)    -- IFNULL(actual_amount,0) - IFNULL(approved_amount, estimated_amount)
  receipt_document_id? fk → documents · notes?
```

Aturan:

- `estimated_amount` tidak dapat diubah setelah plan `approved`.
- `approved_amount` hanya oleh `budget.approve`; total approved disalin ke `dawah_activity_plans.approved_budget`.
- `actual_amount` diisi saat laporan; variance > 10% (konfigurasi) wajib keterangan.

Kategori seed (PRD §17): transportasi, konsumsi, sewa, hadiah, buku, brosur, peralatan, penginapan, media, olahraga, lain-lain.

## 8.4 Materi dakwah

```
dawah_materials
  id pk · uuid uq · tenant_id? fk     -- NULL = materi nasional
  title · title_ar?
  material_type enum(book, leaflet, article, video, audio, poster, presentation, qr, translation, module)
  language_id fk → languages · topic? · target_audience? · description?
  file_document_id? fk → documents · external_url?
  version varchar(20) · parent_material_id? fk → dawah_materials   -- versi sebelumnya
  status enum(draft, in_review, approved, retired)
  approved_by? fk → users · approved_at?
  stock_quantity? int                 -- untuk materi cetak
  created_by fk → users
  idx(language_id, status) · idx(tenant_id, status)

dawah_material_distributions
  id pk · tenant_id fk · activity_plan_id? fk · dai_id fk → dais · material_id fk
  prospect_id? fk · convert_id? fk
  quantity int default 1 · distributed_at · notes?
  idx(material_id, distributed_at)
```

Hanya materi `approved` yang dapat didistribusikan. Materi baru versi = record baru dengan `parent_material_id`; versi lama di-`retired`.

## 8.5 Peserta kegiatan

```
activity_participants
  id pk · activity_plan_id fk
  participant_type enum(prospect, convert, dai, staff, guest)
  participant_id? · participant_name? · phone_encrypted? enc
  attendance_status enum(invited, confirmed, attended, absent, cancelled)
  transportation_required bool · transport_request_id? fk → transport_requests
  notes?
  idx(activity_plan_id, participant_type)
```

Daftar peserta yang dicetak/diekspor menyembunyikan convert dengan disclosure `high_confidentiality` (lihat [04-identity-privacy.md](04-identity-privacy.md)).

## 8.6 Laporan kegiatan

```
dawah_activity_reports
  id pk · activity_plan_id fk uq · dai_id fk → dais
  actual_participants int · actual_start_at? · actual_end_at?
  summary text · achievements? text · challenges? text · follow_up_actions? text
  actual_budget? decimal(14,2) · material_usage_summary? · transport_summary?
  new_prospects_count int default 0 · shahadah_count int default 0
  submitted_at? · status enum(draft, submitted, verified, returned)
  verified_by? fk → users · verified_at? · return_note?
```

## 8.7 Transportasi

```
vehicles
  id pk · tenant_id fk · vehicle_number · plate_number · vehicle_type enum(car, van, minibus, bus)
  capacity int · status enum(available, in_maintenance, retired)
  uq(tenant_id, plate_number)

drivers
  id pk · tenant_id fk · user_id? fk → users · name · phone_encrypted enc
  license_number_encrypted? enc · license_expires_on? · status enum(active, inactive)

activity_transport_requests
  id pk · tenant_id fk · activity_plan_id fk · requested_by fk → users
  transport_type enum(office_vehicle, rental, reimbursement)
  vehicle_count int · passenger_count int · pickup_required bool
  departure_at · return_at?
  status enum(requested, approved, rejected, scheduled, completed, cancelled)
  approved_by? fk → users · notes?

transport_requests                -- pickup individual
  id pk · tenant_id fk · activity_transport_request_id? fk · trip_id? fk → transport_trips
  convert_id? fk · activity_plan_id? fk
  pickup_location_type enum(residence, workplace, custom)
  residence_id? fk → convert_residences · latitude? · longitude? · pickup_address?
  pickup_at · contact_person_id? fk → convert_contact_persons
  vehicle_id? fk → vehicles · driver_id? fk → drivers
  status enum(requested, assigned, picked_up, no_show, completed, cancelled)
  notes?

transport_trips                   -- baru: satu perjalanan kendaraan
  id pk · tenant_id fk · vehicle_id fk · driver_id fk · activity_transport_request_id? fk
  departs_at · returns_at? · status enum(planned, in_progress, completed, cancelled)
  idx(vehicle_id, departs_at) · idx(driver_id, departs_at)
  -- transport_requests.trip_id? fk → transport_trips mengelompokkan pickup ke satu trip
```

Aturan:

- Service menolak penugasan kendaraan/driver yang tumpang tindih waktunya.
- Jumlah pickup per trip ≤ `vehicles.capacity`.
- Lembar pickup untuk driver hanya berisi nama panggilan, titik pickup, dan nomor contact person; tidak berisi status agama atau keterangan syahadat.
