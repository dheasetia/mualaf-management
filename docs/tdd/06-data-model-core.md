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

Seed awal `dai_activity_types` mengikuti PRD §14 (dakwah individu, kunjungan, diskusi, dialog, syahadat, follow-up, mengajar kelas, kajian, mengajar Al-Qur'an, pelatihan, terjemah, menulis buku, artikel, membuat materi, review materi, video/audio, olahraga, turnamen, perlombaan, gathering, perjalanan, makan bersama, kegiatan Ramadhan). Hanya `shahadah` yang `requires_verification`.

Aturan master data (langkah 6, `PlatformMasterSeeder` + `MasterDataPolicy`):

- Seeder hanya menambah entri yang belum ada (dicocokkan dengan kode, termasuk entri soft-deleted); perubahan dan penonaktifan oleh admin Central tidak ditimpa saat seeder dijalankan ulang.
- Isi awal: 13 wilayah Saudi dengan kota utamanya; 249 negara ISO 3166-1 dari `database/data/countries.php` (nama Arab/Inggris dari ICU, kode telepon; Palestina dinamai `فلسطين`/`Palestine`); 42 bahasa ISO 639-1 dengan flag RTL; agama sebelum masuk Islam (tanpa `islam`); 11 kategori anggaran.
- Kode disimpan dalam satu bentuk: huruf besar untuk wilayah, kota, dan ISO negara; huruf kecil untuk bahasa dan kode snake_case (agama, kategori anggaran, jenis aktivitas). Kode unik termasuk terhadap entri soft-deleted.
- Semua user platform dapat membaca layar master data di Central; hanya `master.manage` yang menulis. Tabel dengan `deleted_at` di-soft-delete (wilayah yang masih punya kota/kantor dan kota yang dipakai kantor tidak dapat dihapus); tabel dengan `active` dinonaktifkan, tidak dihapus. Force delete tidak tersedia.

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
  locale varchar(5) default 'ar' · avatar_path? (foto profil di disk privat, JPEG 256×256 tanpa metadata) · last_login_at? · last_login_ip?
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
  uq(tenant_id, employee_number) · uq(tenant_id, user_id)
  idx(tenant_id, status)

dai_languages
  dai_id fk → dais · language_id fk → languages
  proficiency enum(native, fluent, working, basic)
  pk(dai_id, language_id)
```

Aturan (langkah 7, `CreateDai`/`UpdateDai`/`ChangeDaiStatus` di `app/Domain/Dai`):

- Akun user yang ditautkan harus anggota aktif kantor tersebut dan hanya ke satu da'i per kantor (`uq(tenant_id, user_id)`, tambahan v1.1).
- Supervisor harus da'i `active` di kantor yang sama, bukan dirinya sendiri, dan rantai supervisor di atasnya tidak boleh kembali ke da'i tersebut.
- Status: `active ↔ on_leave`, `active/on_leave → inactive`, `inactive → active`. Da'i yang masih Primary aktif untuk prospect/convert tidak dapat dijadikan `inactive` sebelum dialihkan. Da'i tidak pernah dihapus.
- Audit: `dai.created`, `dai.updated` (hanya field yang berubah, termasuk bahasa), `dai.status_changed`; nomor telepon diredaksi.
- Workload (`dais.view_workload`): per da'i `active`/`on_leave`, jumlah assignment Primary aktif per jenis subjek, assignment lain yang aktif, dan da'i aktif yang disupervisi.

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

Implementasi (langkah 14, modul `app/Domain/Companies`): `CompanyScope` — kantor melihat direktori nasional + perusahaannya sendiri, tanpa tenant context hanya nasional (layar Central mengangkat scope untuk daftar promosi). Pemilik diisi saat dibuat dan tidak berubah kecuali `PromoteCompany`. Perusahaan kantor dikelola pemegang `converts.update` atau `prospects.update`; direktori nasional dan promosi oleh `master.manage`. Promosi dengan `registration_number` yang sudah ada di direktori nasional **menggabungkan**: semua referensi (`prospects`, `convert_employments`, `convert_contact_persons`) dipindah ke perusahaan nasional dan salinan kantor dihapus; audit `companies.promoted`/`companies.merged`. Koordinat diambil dari tautan Google Maps (`App\Support\Geo\GoogleMapsLink`, tanpa API key; hanya https dari domain Google) bila lintang/bujur kosong. FK `prospects.company_id` ditambahkan.

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

Aturan (langkah 8, `app/Domain/Prospects`):

- `company_id` dan `convert_id` dibuat tanpa FK; FK ditambahkan bersama tabel `companies` (langkah 14) dan `converts` (langkah 10). Index tambahan `(tenant_id, name_search_key)`.
- `name_search_key` (`App\Support\Text\NameSearchKey`: huruf kecil, tanpa aksen Latin, harakat, dan tatweel; varian alif/ya/ta marbuta disatukan) dan `phone_fingerprint` (`PhoneNumber::fingerprint`: nomor dinormalisasi ke digit internasional, nomor lokal dianggap Saudi, lalu HMAC `IDENTITY_HMAC_KEY` dengan purpose `phone`) dihitung model pada setiap save.
- Telepon yang sama dengan prospect lain di kantor yang sama menghentikan penyimpanan sampai pengguna mengonfirmasi ("mungkin orang yang sama"); kantor lain tidak diperiksa.
- Pemegang `prospects.assign_dai` memilih Primary Da'i saat registrasi; pengguna lain (da'i yang mendaftarkan kontaknya sendiri) otomatis menjadi Primary Da'i dan wajib punya record da'i.
- Pemegang `prospects.view_own` hanya melihat dan menindaklanjuti prospect yang punya assignment aktif dengan record da'i miliknya (`ProspectVisibility`); record lain tidak ditemukan (404).
- `ChangeProspectStatus` mengikuti state machine 12.5 tetapi tidak pernah ke `converted` (RecordShahadah) atau `transferred` (transfer prospect); `not_interested`, `lost_contact`, `closed` wajib alasan. Prospect berstatus terminal hanya bisa dibaca.
- Audit: `prospects.created`, `prospects.updated`, `prospects.status_changed`, `dai.primary_assigned`; histori di `prospect_status_histories` (append-only).

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

Tabel inti `converts` dibuat pada langkah 9 (dibutuhkan FK `convert_identifiers` dan pencarian nasional); `photo_document_id` dan `current_follow_up_status_id` tanpa FK sampai tabelnya ada (langkah 18 dan 24). `origin_tenant_id` dan `current_tenant_id` diisi dari tenant context saat insert; perubahan lewat save biasa melempar `ConvertTenantImmutable`.

Perubahan dari v1.0:

- `phone` dan `email` dihapus; kontak hanya di `convert_contacts` (terenkripsi).
- `status` hanya status *record* administratif. Tidak ada nilai yang merepresentasikan status keimanan; kondisi pembinaan ada di `convert_follow_up_statuses` (PRD §31). Nilai `deceased` dan `record_closed` hanya dapat di-set Head of Dawah dengan alasan.
- `origin_tenant_id` dan `current_tenant_id` dikeluarkan dari mass assignment; observer melempar exception bila berubah di luar service yang berwenang.

Aturan (langkah 10): convert tidak dibuat dari panel (hanya alur syahadat, langkah 11) dan tidak dihapus. `UpdateConvert` mengubah profil; `ChangeDisclosureLevel` (alasan wajib), `ChangeConvertStatus` (`converts.change_status`, alasan wajib, histori `convert_status_histories` append-only dicap `tenant_id` kantor penulis), `AssignConvertDai` (lewat `DaiAssignmentService`). Status `deceased`/`record_closed` final; record tertutup hanya bisa dibaca. Audit: `converts.updated`, `converts.disclosure_changed`, `converts.status_changed`, `converts.contact_added`, `converts.contact_removed`, `dai.primary_assigned`.

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

Kontak (langkah 10): telepon (`mobile`, `whatsapp`) disimpan ternormalisasi `+966…` dengan fingerprint HMAC yang sama seperti telepon prospect; email huruf kecil dan divalidasi. Satu kontak primary per convert; menghapus kontak hanya menonaktifkannya (`active = false`).

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

Implementasi (langkah 14): `SaveConvertLocationRecord` menulis ketiga tabel dari field yang di-whitelist; record `is_current` (employment, residence) atau `is_primary` (contact person) yang baru menonaktifkan yang lama. Telepon kerja dan telepon contact person dienkripsi. Data ini mengikuti convert (`ScopedThroughConvert`), hanya terlihat dengan profil penuh (`ConvertAccess`) dan hanya diubah pemegang `converts.update`; record tertutup tidak dapat diubah. Contact person boleh dihapus (data pihak ketiga, bukan histori) dengan audit; employment dan residence diakhiri lewat `is_current`/`ended_at`.
