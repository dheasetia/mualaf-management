# Runbook Operasional

Panduan menjalankan, memelihara, dan menangani insiden aplikasi manajemen mualaf. Detail desain ada di [docs/tdd](tdd/README.md); runbook ini berisi langkah kerja.

## 1. Komponen

| Komponen | Fungsi | Catatan |
| --- | --- | --- |
| Aplikasi Laravel (PHP 8.4) | Panel Center (`/center/{kode}`), Central (`/central`), verifikasi publik (`/verify/{uuid}`) | `APP_DEBUG=false`, HTTPS wajib |
| MySQL 8.4 | Data utama | Tiga akun: `migrator` (DDL), `dba` (grant), aplikasi (`DB_APP_USERNAME`, tanpa DDL, tanpa UPDATE/DELETE pada tabel append-only) |
| Redis | Session, cache, antrean | Prefix `muallaf:{env}:` |
| Queue worker | Antrean `critical`, `pdf`, `default`, `exports`, `files`, `reports` | Lihat §4 |
| Scheduler | `php artisan schedule:run` tiap menit | Lihat §5 |
| Gotenberg 8 | PDF sertifikat dan surat | `GOTENBERG_URL` |
| ClamAV (clamd) | Scan antivirus unggahan | `CLAMAV_ENABLED=true`, `CLAMAV_HOST`, `CLAMAV_PORT` |
| Object storage privat | Dokumen, PDF, export | Bucket privat + enkripsi sisi server |

## 2. Variabel lingkungan penting

| Variabel | Keterangan |
| --- | --- |
| `APP_KEY` | Enkripsi kolom `*_encrypted` dan cookie. **Rotasi butuh re-enkripsi**, lihat §7 |
| `IDENTITY_HMAC_KEY` | Fingerprint nomor identitas (BR-001). Terpisah dari `APP_KEY`. Rotasi = hitung ulang semua fingerprint |
| `DB_USERNAME` / `DB_APP_USERNAME` | Akun runtime aplikasi; `db:sync-app-grants` menolak akun DBA/migrator |
| `GOTENBERG_URL`, `GOTENBERG_TIMEOUT` | Renderer PDF |
| `CLAMAV_ENABLED`, `CLAMAV_HOST`, `CLAMAV_PORT`, `CLAMAV_TIMEOUT` | Antivirus; produksi wajib `true` |
| `CERTIFICATES_DISK`, `DOCUMENTS_DISK`, `REPORTS_DISK` | Disk privat |
| `TRANSFER_SLA_DAYS` (7), `FOLLOW_UP_QUIET_DAYS` (90), `DAWAH_VARIANCE_NOTE_THRESHOLD` (0.10) | Aturan bisnis |
| `EXPORT_RETENTION_HOURS` (24), `KNOWN_DEVICE_DAYS` (90), `STALE_ACCOUNT_DAYS` (90) | Keamanan |
| `CSP_FONT_HOST` | Host font Filament yang diizinkan CSP (default Bunny Fonts) |

## 3. Deploy

1. Ambil rilis, `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`.
2. `php artisan down --render="errors::503"` bila ada migrasi yang mengunci tabel besar.
3. Migrasi dengan akun DDL: `php artisan migrate --database=migrator --force`.
4. Sinkronkan grant akun aplikasi: `php artisan db:sync-app-grants`.
5. Seeder data nasional bila ada yang baru (idempoten): `CertificateTemplateSeeder`, `DocumentTemplateSeeder`, `SubmissionTypeSeeder`, `FollowUpScheduleTemplateSeeder`.
6. `php artisan config:cache route:cache view:cache event:cache filament:optimize`.
7. `php artisan queue:restart`, lalu `php artisan up`.
8. Cek: `/up` 200, login Center dan Central, `php artisan schedule:list`, worker berjalan, dan `failed_jobs` tidak bertambah.

Rollback: deploy rilis sebelumnya. Migrasi tidak di-rollback di produksi (beberapa `down()` memang menolak); perbaiki dengan migrasi maju.

## 4. Antrean

| Antrean | Isi | Worker | Timeout |
| --- | --- | --- | --- |
| `critical` | Audit async, notifikasi panel (`NotifyOfficeStaff`) | 2 | 60 s |
| `pdf` | `GenerateCertificatePdf`, `GenerateLetterPdf` | 2 | 180 s |
| `default` | Email notifikasi (lockout, perangkat baru) | 2 | 60 s |
| `exports` | `GenerateExport` (payload terenkripsi) | 1 | 900 s |
| `files` | `ScanDocument` (ClamAV) | 1 | 300 s |
| `reports` | Cadangan untuk agregasi | 1 | 1800 s |

Contoh: `php artisan queue:work redis --queue=critical,pdf,default --tries=5 --timeout=180`.

Job gagal: `php artisan queue:failed`, periksa, lalu `queue:retry {id}`. Jangan `queue:flush` tanpa catatan insiden.

## 5. Tugas terjadwal

| Perintah | Jadwal | Fungsi |
| --- | --- | --- |
| `transfers:escalate` | tiap jam | Eskalasi transfer convert melewati SLA |
| `followups:flag-quiet` | 03.00 | Saran follow-up (bukan status) untuk convert lama tak dihubungi (PRD §31) |
| `followups:notify-overdue` | 07.00 | Pengingat follow-up terlambat ke da'i, eskalasi > 7 hari ke Head of Dawah |
| `reports:aggregate` | 01.30 | Statistik harian, mengulang 7 hari terakhir |
| `exports:purge` | tiap jam | Hapus file export kedaluwarsa |
| `access:review` | tanggal 1, 06.00 | Ringkasan review akses ke audit log |

Agregasi ulang manual: `php artisan reports:aggregate --date=2026-11-01` atau `--days=30`.

## 6. Pemantauan

- Log aplikasi melewati `RedactPersonalData` (email, telepon, nomor identitas dimasking). Jangan menambah log yang mem-bypass channel standar.
- Pantau: `failed_jobs`, umur antrean `pdf` dan `files` (dokumen `scan_status = pending` lama berarti ClamAV bermasalah), status export `failed`, sertifikat issued tanpa `file_checksum` lebih dari 10 menit.
- Audit yang perlu alert: `tenancy.bypassed` di luar pola biasa, banyak `auth.new_device`, `document.infected`, `export.downloaded` data sensitif > 1.000 baris, lonjakan national search.

## 7. Kunci dan rahasia

- Simpan di secret manager; `.env` tidak pernah di-commit.
- **`APP_KEY`**: tambahkan kunci lama ke `APP_PREVIOUS_KEYS` lalu ganti `APP_KEY`; data lama tetap terbaca lewat kunci sebelumnya. Kunci lama baru boleh dihapus setelah semua kolom `*_encrypted` dienkripsi ulang — skrip re-enkripsi **belum tersedia** dan harus dibuat serta diuji di staging lebih dulu. Semua sesi akan berakhir.
- **`IDENTITY_HMAC_KEY`**: rotasi membutuhkan perhitungan ulang semua fingerprint identitas dalam jendela pemeliharaan (deteksi duplikat bergantung padanya). Jangan rotasi tanpa skrip migrasi yang sudah diuji di staging.
- Password akun DB dirotasi lewat DBA, lalu `db:sync-app-grants`.

## 8. Backup dan pemulihan

- Backup MySQL terenkripsi + binlog (RPO 1 jam); kunci enkripsi backup terpisah dari storage backup.
- Object storage: versioning + replikasi.
- **Uji restore tiap kuartal**: restore ke lingkungan terisolasi, jalankan `php artisan migrate:status`, login, buka satu sertifikat dan verifikasi QR-nya, lalu catat waktu pemulihan.

## 9. Review akses (bulanan)

1. Baca ringkasan `access.reviewed` di audit log, atau jalankan `php artisan access:review`.
2. Di tiap kantor, Local Auditor membuka **Laporan → Review akses**: akun dengan role sensitif tanpa authenticator, dan akun tidak dipakai > 90 hari.
3. Center Admin mencabut role yang tidak perlu dan menonaktifkan akun tak terpakai; catat tindakan di tiket.

## 10. Penanganan insiden

| Kejadian | Langkah awal |
| --- | --- |
| Dugaan kebocoran lintas kantor | Simpan bukti (request id `X-Request-Id`, audit log), nonaktifkan fitur terkait, jalankan `pest --group=tenant-isolation`, laporkan ke penanggung jawab data |
| Akun diambil alih | Nonaktifkan akun, cabut sesi (ganti password memicu `AuthenticateSession`), reset MFA, tinjau audit akun 30 hari |
| `document.infected` | Pastikan file sudah terhapus, beri tahu pengunggah, periksa unggahan lain dari akun yang sama |
| Export bocor | File berumur ≤ 24 jam dan terenkripsi AES-256; minta penerima menghapus, catat `export.downloaded` |
| Sertifikat palsu dilaporkan | Cek di `/verify`; bila sah tetapi disalahgunakan, cabut lewat panel (status `revoked` tampil di verifikasi) |
| PDF tidak terbit | Cek Gotenberg (`curl $GOTENBERG_URL/health`), antrean `pdf`, lalu regenerasi dari panel |
| ClamAV mati | File tetap `pending` (tidak bisa diverifikasi/diunduh); pulihkan clamd, job akan mencoba ulang |

## 11. Sebelum pilot dan setiap gelombang rollout

- [ ] Pentest eksternal dengan fokus tenant isolation dan IDOR (13 §13.5); tindak lanjuti temuan sebelum go-live.
- [ ] Suite lengkap hijau, termasuk `tests/TenantIsolation/IdorSweepTest.php` dan suite `Concurrency`.
- [ ] Load test k6 (`tests/Load/center.js`) di staging berisi `LoadTestSeeder` memenuhi target 15 §15.1.
- [ ] `CLAMAV_ENABLED=true`, Gotenberg berjalan, HTTPS + HSTS aktif, `APP_DEBUG=false`.
- [ ] Uji restore backup terakhir lulus.
- [ ] Review akses dilakukan untuk kantor yang masuk gelombang.
