# 06. Data Model — Core

Notasi: `pk` primary key, `fk → t` foreign key, `uq` unique, `idx` index, `?` nullable, `enc` encrypted (Laravel cast), `gen` generated column. Semua tabel memiliki `created_at`/`updated_at` kecuali disebut append-only. Tipe status adalah `varchar(40)` berisi nilai enum yang tercantum.

## 6.1 Master data (global)

```
regions
  id pk · code varchar(10) uq · name_ar · name_en · sort_order int · deleted_at?

cities
  id pk · region_id fk → regions · code varchar(20) uq · name_ar · name_en
  latitude? decimal(10,7) · longitude? decimal(10,7) · deleted_at?

countries
  id pk · iso2 char(2) uq · iso3 char(3) uq · name_ar · name_en · phone_code? · deleted_at?

languages
  id pk · code varchar(10) uq (ISO 639) · name_ar · name_en · is_rtl bool · deleted_at?

religions
  id pk · code varchar(40) uq · name_ar · name_en · active bool · sort_order

budget_categories
  id pk · code uq · name_ar · name_en · active bool

dai_activity_types
  id pk · code uq · name_ar · name_en
  category enum(dawah, education, scholarly_media, social) · requires_verification bool · active bool
```

Seed awal `dai_activity_types` mengikuti PRD §14 (dakwah individu, kunjungan, diskusi, dialog, syahadat, follow-up, mengajar kelas, kajian, mengajar Al-Qur'an, pelatihan, terjemah, menulis buku, artikel, membuat materi, review materi, video/audio, olahraga, turnamen, perlombaan, gathering, perjalanan, makan bersama, kegiatan Ramadhan).

## 6.2 Tenants

```
tenants
  id pk · uuid uq · code varchar(10) uq · name_ar · name_en
  region_id fk → regions · city_id fk → cities
  address? · phone? · email? · latitude? · longitude? · logo_path? · director_name?
  status enum(draft, active, suspended, archived) · activated_at? · suspended_at?
  idx(status)

tenant_settings
  id pk · tenant_id fk → tenants uq · settings json
```

`settings` berisi konfigurasi kantor yang tervalidasi skema (`TenantSettingsData`): default guidance program, follow-up schedule template, numbering prefix, working days, bahasa layanan. Pengaturan yang memengaruhi aturan nasional (duplicate threshold, retensi) hanya di konfigurasi platform.

## 6.3 Users dan membership

```
users
  id pk · uuid uq · name · email uq · username? uq · phone_encrypted? enc
  password · email_verified_at? · is_active bool
  mfa_secret? enc · mfa_recovery_codes? enc · mfa_confirmed_at?
  locale varchar(5) default 'ar' · last_login_at? · last_login_ip?
  remember_token?

user_tenant_memberships
  id pk · user_id fk → users · tenant_id fk → tenants
  status enum(active, suspended, ended) · joined_at · left_at?
  active_membership_key gen uq   -- IF(status='active', CONCAT(user_id,':',tenant_id), NULL)
```

Relasi: users N:M tenants via membership. Role platform tidak memerlukan membership.

## 6.4 RBAC

```
roles
  id pk · code varchar(60) uq · name_ar · name_en
  scope_type enum(platform, tenant) · is_system bool · description?

permissions
  id pk · code varchar(100) uq · module varchar(40) · action varchar(60) · description?

role_permissions
  role_id fk → roles (cascade) · permission_id fk → permissions (cascade)
  pk(role_id, permission_id)

user_role_assignments
  id pk · user_id fk → users · role_id fk → roles · tenant_id? fk → tenants
  assigned_by fk → users · assigned_at · revoked_at? · revoked_by? fk → users · revoke_reason?
  active_assignment_key gen uq
  -- aturan aplikasi: role scope 'tenant' wajib tenant_id; role 'platform' wajib tenant_id NULL
```

Role sistem (seed, `is_system = true`): Super Admin, Central Admin, Central Auditor, Central Reporting, Support Officer (platform); Center Admin, Head of Dawah, Da'i, Registration Officer, Shahadah Verifier, Guidance Coordinator, Teacher, Examination Officer, Certificate Officer, Administrative Officer, Approver, Transportation Officer, Pilgrimage Officer, Local Auditor (tenant).

## 6.5 Da'i

```
dais
  id pk · uuid uq · tenant_id fk → tenants · user_id? fk → users
  employee_number · full_name · full_name_ar? · gender enum(male, female)
  nationality_id? fk → countries · phone_encrypted? enc · email?
  education? · specialties json · supervisor_dai_id? fk → dais
  joined_at? · employment_status enum(full_time, part_time, volunteer, contract)
  status enum(active, on_leave, inactive)
  uq(tenant_id, employee_number)
  idx(tenant_id, status)

dai_languages
  dai_id fk → dais · language_id fk → languages
  proficiency enum(native, fluent, working, basic)
  pk(dai_id, language_id)
```

Perubahan dari v1.0: `languages` JSON diganti tabel pivot agar bisa dicari ("da'i yang berbahasa Tagalog") dan dipakai untuk saran penugasan. `gender` ditambahkan untuk mendukung aturan pencocokan gender bila disetujui (open question).

## 6.6 Companies

```
companies
  id pk · uuid uq · tenant_id? fk → tenants   -- NULL = direktori nasional
  name_ar · name_en? · registration_number? · phone? · email? · website?
  city_id? fk → cities · address? · district? · postal_code?
  latitude? · longitude? · google_maps_url? · notes?
  idx(name_ar) · idx(city_id) · idx(registration_number)
```

Perusahaan yang dibuat kantor bersifat milik kantor; Central dapat mempromosikan ke direktori nasional (dedup berdasarkan `registration_number`).

## 6.7 Prospects

```
prospects
  id pk · uuid uq · tenant_id fk → tenants
  full_name · name_search_key · gender enum(male, female)
  nationality_id? fk → countries · native_language_id? fk → languages
  current_religion_id? fk → religions · current_religion_other?
  phone_encrypted? enc · phone_fingerprint? binary(32) · email_encrypted? enc
  company_id? fk → companies · occupation?
  residence_city_id? fk → cities · residence_description?
  primary_dai_id fk → dais          -- salinan dari dai_assignments, ditulis DaiAssignmentService
  source_type enum(walk_in, dai_contact, referral_convert, referral_company, event, online, other)
  source_description?
  interest_level enum(low, medium, high) ?
  status enum(new, contacted, active_follow_up, learning_islam, considering_islam,
              ready_for_shahadah, converted, not_interested, lost_contact, transferred, closed)
  first_contact_at? · last_contact_at? · next_follow_up_at?
  converted_at? · convert_id? fk → converts
  created_by fk → users
  idx(tenant_id, status) · idx(primary_dai_id, next_follow_up_at) · idx(phone_fingerprint)

prospect_status_histories            -- append-only
  id pk · prospect_id fk · tenant_id fk · from_status? · to_status · reason? · changed_by fk → users · created_at
```

Perubahan dari v1.0: kontak dienkripsi; `phone_fingerprint` untuk deteksi prospect ganda di kantor yang sama; histori status ditambahkan.

## 6.8 Converts

```
converts
  id pk · uuid uq
  origin_tenant_id fk → tenants      -- immutable, hanya via origin_correction_requests
  current_tenant_id fk → tenants     -- hanya via TransferService
  full_name · name_ar? · previous_name? · islamic_name? · name_search_key
  gender enum(male, female) · date_of_birth? date
  nationality_id fk → countries · native_language_id? fk → languages
  previous_religion_id? fk → religions · previous_religion_other?
  status enum(active, inactive, deceased, record_closed)
  disclosure_risk_level enum(normal, restricted, high_confidentiality) default 'normal'
  primary_dai_id fk → dais           -- salinan dari dai_assignments
  prospect_id? fk → prospects uq
  photo_document_id? fk → documents
  current_follow_up_status_id? fk → convert_follow_up_statuses   -- pointer ke status terbaru (lihat 10 §10.3)
  created_by fk → users
  idx(current_tenant_id, status) · idx(origin_tenant_id, created_at)
  idx(primary_dai_id, status) · idx(name_search_key)
```

Perubahan dari v1.0:

- `phone` dan `email` dihapus; kontak hanya di `convert_contacts` (terenkripsi).
- `status` hanya status *record* administratif. Tidak ada nilai yang merepresentasikan status keimanan; kondisi pembinaan ada di `convert_follow_up_statuses` (PRD §31). Nilai `deceased` dan `record_closed` hanya dapat di-set Head of Dawah dengan alasan.
- `origin_tenant_id` dan `current_tenant_id` dikeluarkan dari mass assignment; observer melempar exception bila berubah di luar service yang berwenang.

## 6.9 Convert identity dan kontak

```
convert_identifiers
  id pk · convert_id fk → converts
  identity_type enum(national_id, iqamah, passport, gcc_id, border_number, other)
  identity_number_encrypted enc · identity_last4 char(4)
  identity_fingerprint binary(32) · fingerprint_key_version tinyint
  issuing_country_id fk → countries · expires_on? date
  is_primary bool · verification_status enum(unverified, verified, rejected)
  verified_by? fk → users · verified_at? · replaced_at? · replaced_reason?
  active_fingerprint gen binary(32)   -- IF(replaced_at IS NULL, identity_fingerprint, NULL)
  primary_convert_key gen             -- IF(is_primary AND replaced_at IS NULL, convert_id, NULL)
  uq(identity_type, issuing_country_id, active_fingerprint)
  uq(primary_convert_key)

convert_contacts
  id pk · convert_id fk · contact_type enum(mobile, whatsapp, email, telegram, other)
  value_encrypted enc · value_fingerprint binary(32) · is_primary bool
  verified_at? · active bool
  idx(value_fingerprint)
```

## 6.10 Pekerjaan, tempat tinggal, contact person

```
convert_employments
  id pk · convert_id fk · company_id? fk → companies · company_name_free?
  employee_number? · occupation? · department? · work_location_description?
  work_city_id? fk → cities · work_latitude? · work_longitude? · work_google_maps_url?
  work_phone_encrypted? enc
  employment_status enum(employed, ended, unknown) · started_at? · ended_at? · is_current bool

convert_residences
  id pk · convert_id fk
  residence_type enum(company_dormitory, workers_camp, house, apartment, other)
  name? · city_id? fk → cities · address? · district? · building_no? · room_no?
  latitude? · longitude? · google_maps_url?
  is_pickup_point bool · pickup_instructions?
  started_at? · ended_at? · is_current bool · notes?

convert_contact_persons
  id pk · convert_id fk · company_id? fk → companies · residence_id? fk → convert_residences
  name · position?
  contact_role enum(dormitory_head, hr, public_relations, supervisor, friend, family, other)
  phone_encrypted? enc · alternate_phone_encrypted? enc · email?
  preferred_language_id? fk → languages
  permission_to_contact bool default false
  disclosure_scope enum(none, general_support, knows_conversion) default 'none'
  is_primary bool · notes?
```

Aturan aplikasi: maksimal satu `is_current = true` per convert untuk employment utama dan residence utama (ditegakkan service; boleh dipertegas dengan generated column bila diperlukan).
