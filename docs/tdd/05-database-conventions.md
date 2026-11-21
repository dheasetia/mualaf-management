# 05. Konvensi Database dan Pola Constraint MySQL

MySQL 8.4 tidak mendukung partial/filtered unique index, check constraint lintas tabel, atau deferred constraint. Aturan "satu aktif per X" ditegakkan dengan generated column + unique index; aturan lain ditegakkan di domain service dalam transaksi.

## 5.1 Konvensi umum

| Topik | Konvensi |
| --- | --- |
| Engine / charset | InnoDB, `utf8mb4`, `utf8mb4_0900_ai_ci` |
| Primary key | `id BIGINT UNSIGNED AUTO_INCREMENT` |
| Public identifier | `uuid CHAR(36)` (UUIDv7, urut waktu) `UNIQUE`, dipakai di URL dan API |
| Foreign key | Selalu didefinisikan; `ON DELETE RESTRICT` default; `CASCADE` hanya untuk tabel anak murni (misal `role_permissions`) |
| Timestamps | `created_at`, `updated_at` `TIMESTAMP` UTC; tabel append-only hanya `created_at` |
| Soft delete | Hanya untuk master data dan template; tidak untuk histori, audit, identitas, atau convert |
| Status | `VARCHAR(40)` berisi nilai backed enum PHP; bukan `ENUM` MySQL (agar perubahan nilai tidak butuh ALTER) |
| Boolean | `TINYINT(1)` dengan default eksplisit |
| Uang | `DECIMAL(14,2)` + `currency CHAR(3) DEFAULT 'SAR'` bila relevan |
| Koordinat | `latitude DECIMAL(10,7)`, `longitude DECIMAL(10,7)`; opsional `location POINT SRID 4326` untuk query jarak (fase 6) |
| JSON | Hanya untuk konfigurasi/metadata; data yang di-query wajib kolom sendiri |
| Encrypted | Kolom `*_encrypted TEXT`; fingerprint `*_fingerprint BINARY(32)` ber-index |
| Nama tabel | snake_case jamak Inggris; pivot = gabungan alfabetis bila murni pivot |
| Nama index | `idx_<table>_<cols>`, unique `uq_<table>_<purpose>` |

## 5.2 Pola "satu aktif per X"

```sql
-- Contoh: hanya satu transfer aktif per convert
ALTER TABLE convert_transfer_requests
  ADD COLUMN active_convert_key BIGINT UNSIGNED
    GENERATED ALWAYS AS (
      IF(status IN ('pending','under_review','approved','accepted','escalated'), convert_id, NULL)
    ) STORED,
  ADD UNIQUE KEY uq_transfer_one_active (active_convert_key);
```

Di Laravel migration:

```php
$table->unsignedBigInteger('active_convert_key')
    ->storedAs("IF(status IN ('pending','under_review','approved','accepted','escalated'), convert_id, NULL)");
$table->unique('active_convert_key', 'uq_transfer_one_active');
```

Daftar penerapan:

| Tabel | Aturan | Generated column |
| --- | --- | --- |
| `convert_identifiers` | Satu fingerprint aktif nasional | `active_fingerprint` |
| `convert_identifiers` | Satu primary per convert | `primary_convert_key` |
| `convert_transfer_requests` | Satu transfer aktif per convert | `active_convert_key` |
| `prospect_transfer_requests` | Satu transfer aktif per prospect | `active_prospect_key` |
| `dai_assignments` | Satu Primary Da'i aktif per subjek | `active_primary_key = IF(is_primary AND ended_at IS NULL, CONCAT(subject_type,':',subject_id), NULL)` |
| `shahadah_records` | Satu syahadat pertama aktif per convert | `active_first_key = IF(record_type='first' AND status <> 'voided', convert_id, NULL)` |
| `user_tenant_memberships` | Satu membership aktif per user + tenant | `active_membership_key = IF(status='active', CONCAT(user_id,':',tenant_id), NULL)` |
| `enrollments` | Satu enrollment aktif per convert + program | `active_enrollment_key` |
| `user_role_assignments` | Tidak duplikat role aktif | `active_assignment_key = IF(revoked_at IS NULL, CONCAT(user_id,':',role_id,':',IFNULL(tenant_id,0)), NULL)` |

Batasan MySQL yang perlu diingat: stored generated column tidak boleh merujuk kolom `AUTO_INCREMENT`, dan kolom dasar generated column tidak boleh dipakai FK dengan `ON DELETE/UPDATE CASCADE` atau `SET NULL`. Pola di atas memenuhi keduanya karena merujuk kolom FK dengan `RESTRICT`.

Pengecualian konvensi PK: tabel `notifications` memakai `id CHAR(36)` sesuai skema standar Laravel.

Generated column tidak boleh di-set dari aplikasi; model menambahkannya ke `$guarded`.

## 5.3 Transaksi dan locking

- Transaksi kritis memakai `DB::transaction(fn, attempts: 3)` untuk retry otomatis saat deadlock (1213).
- Urutan lock tetap untuk mencegah deadlock: `converts` → `convert_transfer_requests` → `dai_assignments` → `number_sequences`.
- Lock memakai `lockForUpdate()` pada baris yang sudah ada saja.
- Isolation level default `REPEATABLE READ`; query pengecekan di dalam transaksi memakai locking read.

## 5.4 Append-only

Tabel append-only: `audit_logs`, `convert_status_histories`, `convert_transfer_events`, `convert_transfer_histories`, `certificate_events`, `submission_events`, `pilgrimage_events`, `convert_follow_up_statuses`, `prospect_status_histories`.

Penegakan:

1. User database aplikasi (`muallaf_app`) hanya mendapat `SELECT, INSERT` pada tabel tersebut (tanpa `UPDATE, DELETE`). Grant dikelola oleh migration khusus yang dijalankan user owner.
2. Trigger `BEFORE UPDATE` dan `BEFORE DELETE` yang `SIGNAL SQLSTATE '45000'` sebagai lapis kedua.
3. Model memakai trait `AppendOnly` yang melempar exception pada `update()`/`delete()`.

## 5.5 Polymorphic relation

- `Relation::enforceMorphMap()` di `AppServiceProvider`, alias pendek: `prospect`, `convert`, `dai`, `activity_plan`, `submission`, `enrollment`, `certificate`, `user`.
- Kolom `*_type VARCHAR(40)` + `*_id BIGINT UNSIGNED`, index gabungan `(type, id)`.
- Karena tidak ada FK pada polymorphic, domain service memvalidasi keberadaan dan tenant subjek sebelum insert.

## 5.6 Migrasi

- Satu migration per tabel; constraint lintas modul (FK ke tabel modul lain) di migration terpisah bila urutan perlu.
- Migration produksi wajib *online-safe*: tambahkan kolom nullable → backfill via job → set NOT NULL di rilis berikutnya. Gunakan `ALGORITHM=INSTANT`/`INPLACE` bila didukung.
- `php artisan migrate --pretend` dijalankan di CI terhadap snapshot skema staging (migration check).
- Tidak ada `down()` destruktif untuk tabel identitas/histori di produksi (dibiarkan melempar exception).

## 5.7 Index strategy

Index minimum (di luar FK yang otomatis ber-index):

```
converts(current_tenant_id, status)
converts(origin_tenant_id, created_at)
converts(primary_dai_id, status)
converts(name_search_key)
convert_identifiers(identity_type, issuing_country_id, active_fingerprint)   -- unique
prospects(tenant_id, status)
prospects(primary_dai_id, next_follow_up_at)
convert_transfer_requests(convert_id, status)
convert_transfer_requests(to_tenant_id, status)
convert_transfer_requests(from_tenant_id, status)
dai_assignments(dai_id, ended_at)
dai_follow_ups(dai_id, next_follow_up_at)
dai_follow_ups(subject_type, subject_id, occurred_at)
dai_daily_activities(dai_id, activity_date)
dawah_activity_plans(tenant_id, planned_date, status)
enrollments(service_tenant_id, status)
attendances(class_session_id, enrollment_id)                                  -- unique
learning_progress(enrollment_id, lesson_id)                                   -- unique
certificates(certificate_number)                                              -- unique
certificates(verification_token_hash)                                         -- unique
submissions(tenant_id, status, created_at)
convert_follow_up_schedules(dai_id, scheduled_at, status)
convert_long_term_follow_ups(convert_id, follow_up_at)
audit_logs(tenant_id, created_at)
audit_logs(subject_type, subject_id, created_at)
audit_logs(actor_id, created_at)
```

Index final divalidasi dengan `EXPLAIN ANALYZE` pada dataset uji performa ([15-operations.md](15-operations.md)).

## 5.8 Partitioning

- `audit_logs`: `PARTITION BY RANGE (TO_DAYS(created_at))` bulanan bila volume > 50 juta baris. Konsekuensi MySQL: partitioned table tidak boleh punya FK dan setiap unique key wajib memuat kolom partisi, sehingga `audit_logs` tanpa FK (referensi divalidasi aplikasi) dan PK menjadi `(id, created_at)`.
- `attendances` (target 10 juta baris) tidak dipartisi: unique `(class_session_id, enrollment_id)` dan FK lebih penting; volume ini masih nyaman untuk InnoDB dengan index yang tepat.
- Keputusan partitioning diambil setelah load test; skema awal tanpa partisi tetapi PK sudah disiapkan.
