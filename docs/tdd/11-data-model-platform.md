# 11. Data Model — Platform

Notasi sama dengan [06-data-model-core.md](06-data-model-core.md).

## 11.1 Audit log

```
audit_logs                       -- append-only, tanpa soft delete
  id bigint · created_at timestamp(6)   -- pk(id, created_at) bila dipartisi
  tenant_id? · actor_id? · actor_type enum(user, system, job)
  action varchar(80)             -- converts.created, transfer.approved, identity.revealed, ...
  subject_type? · subject_id?
  old_values_redacted? json · new_values_redacted? json
  privileged_grant_id? · ip_address? varbinary(16) · user_agent? varchar(255)
  request_id char(36)
  idx(tenant_id, created_at) · idx(subject_type, subject_id, created_at) · idx(actor_id, created_at)
```

Operasi yang wajib diaudit (minimum):

| Kategori | Action |
| --- | --- |
| Identitas | `identity.national_search`, `identity.revealed`, `identity.added`, `identity.replaced` |
| Convert | `converts.created`, `converts.updated`, `converts.disclosure_changed`, `converts.status_changed`, `converts.origin_corrected` |
| Syahadat | `shahadah.recorded`, `shahadah.verified`, `shahadah.voided` |
| Transfer | `transfer.requested`, `transfer.reviewed`, `transfer.approved`, `transfer.rejected`, `transfer.accepted`, `transfer.completed`, `transfer.escalated` |
| Da'i | `dai.primary_assigned` |
| Sertifikat/dokumen | `certificate.issued`, `certificate.revoked`, `document.generated`, `document.downloaded` |
| Akses | `auth.login`, `auth.login_failed`, `auth.mfa_failed`, `roles.assigned`, `roles.revoked`, `privileged.requested`, `privileged.approved`, `privileged.accessed` |
| Data | `export.requested`, `export.downloaded`, `import.completed` |

Audit ditulis secara sinkron dalam transaksi yang sama untuk operasi kritis; untuk event baca (download, view sensitif) ditulis via queue `audit` dengan context lengkap.

## 11.2 Penomoran

```
number_sequences                 -- baru
  id pk · tenant_id? fk · sequence_type varchar(30)   -- registration, plan, submission, certificate_shahadah, ...
  period varchar(10)             -- '2026' atau 'all'
  next_value bigint unsigned default 1
  uq(tenant_id, sequence_type, period)
```

Implementasi: `App\Support\Numbering\NumberAllocator::next(tenantId, type, period)`; `tenant_id` disimpan `0` untuk sequence nasional karena unique key MySQL menganggap NULL berbeda-beda. Memanggilnya di luar transaksi melempar exception.

`NumberAllocator::next(tenant, type)`:

1. Dalam transaksi pemanggil, `SELECT ... FOR UPDATE` baris sequence (dibuat dengan `INSERT ... ON DUPLICATE KEY UPDATE` bila belum ada).
2. Ambil `next_value`, increment.
3. Format sesuai pola tipe (`{TENANT}-CERT-SH-{YYYY}-{000001}`).

Nomor tidak dipakai ulang; nomor dari transaksi yang di-rollback ikut ter-rollback sehingga tidak ada celah. Unique index pada kolom nomor menjadi lapis kedua.

## 11.3 Notifikasi

```
notifications                    -- skema standar Laravel
  id char(36) pk · type · notifiable_type · notifiable_id
  data json · read_at? · created_at · updated_at
  tenant_id? fk                  -- tambahan, diisi dari TenantContext
  idx(notifiable_type, notifiable_id, read_at)
```

Isi notifikasi tidak memuat nomor identitas, agama sebelumnya, atau detail syahadat; cukup referensi (`uuid`) dan teks umum ("ada permintaan transfer baru").

Implementasi: notifikasi database Filament (lonceng di panel Center dan Central, polling 60 detik) lewat `OfficeAlert` (teks dari `lang/*/notifications.php` dalam bahasa penerima + tautan ke panel kantor penerima). `tenant_id` diisi dari data notifikasi atau TenantContext; `User::notifications()` hanya menampilkan notifikasi kantor dalam konteks (Central: tanpa kantor), sehingga staf beberapa kantor tidak melihat notifikasi campur. Penerima dihitung `OfficeRecipients` dari role aktif yang memegang permission di kantor tersebut. Tautan dibuat lapisan UI lewat kontrak `OfficeLinks`.

## 11.4 Privileged access

```
privileged_access_requests
  id pk · uuid uq · requested_by fk → users · tenant_id? fk
  resource_type · resource_id? · reason text · requested_scope json
  requested_duration_minutes int (≤ 1440)
  status enum(pending, approved, rejected, active, expired, revoked)
  approved_by? fk → users · approved_at? · expires_at? · revoked_at? · revoked_by?
  idx(requested_by, status) · idx(status, expires_at)
```

## 11.5 Export dan import

```
export_jobs
  id pk · uuid uq · tenant_id? fk · requested_by fk
  export_type · filters json · row_count? int
  status enum(queued, processing, ready, failed, expired)
  storage_path? · completed_at? · expires_at?   -- default 24 jam
  downloaded_at? · download_count int default 0

import_batches
  id pk · tenant_id fk · import_type · original_filename · storage_path
  status enum(uploaded, validating, validated, importing, completed, failed, cancelled)
  total_rows · success_rows · failed_rows · uploaded_by fk · completed_at?

import_rows
  id pk · batch_id fk · row_number int · source_data_encrypted enc
  status enum(pending, valid, invalid, imported, duplicate, skipped)
  error_message? · resulting_record_type? · resulting_record_id?
```

Export yang memuat data sensitif memerlukan `reports.export` + alasan, menghormati disclosure level, dan file dienkripsi (zip AES-256 dengan password terpisah atau link sekali pakai). Import convert historis wajib melalui duplicate check nasional per baris.

## 11.6 Log integrasi dan job

```
integration_logs
  id pk · tenant_id? · integration_name · direction enum(inbound, outbound)
  endpoint_reference · request_reference? · response_code? · status
  error_message? · created_at
  -- tidak menyimpan secret atau payload sensitif lengkap

background_job_logs
  id pk · tenant_id? · job_type · reference_type? · reference_id?
  status enum(queued, running, succeeded, failed) · attempts tinyint
  started_at? · finished_at? · error_summary?
```

## 11.7 Retensi data

```
data_retention_rules
  id pk · data_category uq · retention_period varchar(20)   -- ISO 8601 duration, misal P10Y
  disposition_action enum(delete, anonymize, archive, review)
  policy_reference? · active bool · approved_by? fk · approved_at?
```

Usulan awal (menunggu persetujuan kebijakan): audit log 7 tahun; export file 24 jam; import rows 90 hari setelah selesai; dokumen submission 5 tahun setelah selesai; data convert tidak dihapus otomatis (record keagamaan permanen), hanya anonimisasi atas permintaan resmi.

## 11.8 Reporting

```
report_daily_tenant_stats        -- diisi job agregasi malam
  tenant_id · stat_date date · metric varchar(60) · value bigint
  pk(tenant_id, stat_date, metric)

report_daily_national_stats
  stat_date · metric · dimension varchar(60) · dimension_value varchar(60) · value bigint
  pk(stat_date, metric, dimension, dimension_value)
```

Implementasi (langkah 25, `Reporting\Services\DailyStatistics`): `php artisan reports:aggregate` (01.30 setiap malam, mengulang 7 hari terakhir agar verifikasi terlambat masuk ke hari yang benar; `--date=` untuk satu hari) mengganti baris hari itu sehingga aman dijalankan ulang. Hari mengikuti zona waktu tampilan. Metrik alur: `shahadah_verified`, `prospects_created`, `follow_ups_recorded` (follow-up da'i + jangka panjang), `enrollments_completed` (kantor pelaksana), `certificates_issued`, `plans_completed`, `submissions_completed`; snapshot akhir hari: `converts_active` (kantor saat ini), `prospects_open`, `follow_ups_overdue`. Nasional: dimensi `all` untuk semua metrik, `nationality` untuk `shahadah_verified`, dan `converts_total`. Dashboard (`ReportReader`) hanya membaca tabel agregat.

Export (`Reporting\Actions\Exports`): `reports.export`, maksimal 5 per jam per pengguna; jenis `converts` (data pribadi: wajib alasan, hanya dari kantor, mengikuti `ConvertAccess`, tanpa convert `high_confidentiality`, tanggal syahadat hanya dengan `converts.view_sensitive`) dan `shahadah_statistics` (dari tabel agregat, kantor atau nasional). File CSV (sel formula dinetralkan) dalam zip AES-256 di `exports/{uuid}.zip`; kata sandi acak ditampilkan sekali dan tidak disimpan (payload job dienkripsi); tanpa dukungan AES export gagal, tidak pernah ditulis polos. Unduh hanya oleh peminta lewat signed URL 5 menit, dihitung dan diaudit; `php artisan exports:purge` (tiap jam) menghapus file setelah 24 jam. Kolom tambahan `export_jobs.reason`, `error_summary`. Import belum diimplementasikan.

Statistik "masuk Islam" selalu dihitung dari `shahadah_records` (`record_type = first`, `status = verified`) dikelompokkan per `tenant_id` (kantor asal), sehingga tidak terhitung dua kali dan tidak berpindah saat transfer. Mualaf unik nasional = `COUNT(*)` `converts` tanpa duplikasi karena BR-001.
