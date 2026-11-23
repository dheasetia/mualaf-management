# 15. Operasional

## 15.1 Target performa

Dataset uji beban awal:

| Entitas | Jumlah |
| --- | --- |
| Tenant | 150 |
| User | 3.000 |
| Convert | 500.000 |
| Attendance | 10.000.000 |
| Dokumen | 2.000.000 |
| User aktif bersamaan | 300 |

| Operasi | Target p95 |
| --- | --- |
| Halaman list Filament (25 baris, filter tenant) | < 500 ms |
| Detail convert | < 400 ms |
| National identity search | < 200 ms |
| Registrasi syahadat (transaksi) | < 800 ms |
| Penyelesaian transfer | < 800 ms |
| Dashboard kantor (dari tabel agregat) | < 1 s |
| Dashboard nasional (dari tabel agregat) | < 2 s |
| PDF sertifikat (async) | < 10 s sejak issue |

Target divalidasi dengan k6 atau Locust di staging berukuran produksi. Seeder `LoadTestSeeder` membangkitkan dataset sintetis (kantor `LOAD`, ukuran lewat `LOAD_DAIS`/`LOAD_CONVERTS`/`LOAD_PROSPECTS`, menolak berjalan di produksi). Skrip k6: `tests/Load/center.js` (ambang p95 sesuai tabel di atas; butuh cookie sesi Center Admin kantor `LOAD`). Load test belum dijalankan di staging.

Praktik:

- Hindari N+1: `Model::preventLazyLoading()` di non-produksi.
- List Filament memakai kolom ber-index untuk sort/filter default; pencarian teks memakai `name_search_key` (prefix) bukan `LIKE '%x%'` pada tabel besar.
- Dashboard membaca `report_daily_*`, bukan agregasi live.
- Read replica dipakai untuk reporting dan export setelah fase 9.

## 15.2 Backup dan recovery

| Target | Nilai usulan |
| --- | --- |
| RPO | 1 jam |
| RTO | 4 jam |

| Komponen | Strategi |
| --- | --- |
| MySQL | Full backup harian (Percona XtraBackup atau managed snapshot) + binlog kontinu (ROW format, retensi 7 hari) |
| Object storage | Versioning bucket + replikasi ke lokasi kedua |
| Enkripsi | Semua backup terenkripsi; kunci di secret manager terpisah |
| Salinan off-system | Harian ke akun/region terpisah |
| Uji restore | Bulanan (restore penuh ke environment terisolasi + checklist verifikasi), dilaporkan |

## 15.3 Environment

| Environment | Tujuan | Data |
| --- | --- | --- |
| Local | Pengembangan (Herd + PhpStorm) | Seeder sintetis |
| Development | Integrasi fitur | Sintetis |
| Staging | UAT, load test, migration check | Sintetis berukuran produksi; data produksi hanya bila dianonimkan dan disetujui |
| Production | Operasional | Nyata |

Data produksi tidak boleh disalin ke development/local. Konfigurasi per environment via secret manager; perbedaan `.env` didokumentasikan di `.env.example`.

## 15.4 CI/CD

| # | Tahap | Alat | Gagal bila |
| --- | --- | --- | --- |
| 1 | Lint | `vendor/bin/pint --test` | Ada perubahan format |
| 2 | Static analysis | `vendor/bin/phpstan analyse` | Error baru di luar baseline |
| 3 | Security audit | `composer audit`, `npm audit --omit=dev` | Advisory high/critical |
| 4 | Unit tests | `pest --testsuite=Unit` | Ada yang gagal |
| 5 | Feature tests | `pest --testsuite=Feature --parallel` | Ada yang gagal, coverage < 80% |
| 6 | Tenant isolation tests | `pest --group=tenant-isolation` | Ada yang gagal |
| 7 | Concurrency tests | `pest --group=concurrency` | Ada yang gagal |
| 8 | Build | `npm ci && npm run build`, `composer install --no-dev -o` | Build gagal |
| 9 | Staging deploy | Zero-downtime (Envoyer/Deployer/container rolling) | Health check gagal |
| 10 | Migration check | `migrate --pretend` terhadap staging + review | Operasi blocking pada tabel besar |
| 11 | Approval | Manual oleh pemilik produk + tech lead | — |
| 12 | Production deploy | Rolling, `migrate --force`, `horizon:terminate`, `optimize` | Health check gagal → rollback |

CI memakai service container MySQL 8.4 dan Redis yang sama versinya dengan produksi.

## 15.5 Monitoring

| Sinyal | Alat (usulan) | Alert |
| --- | --- | --- |
| Uptime | Health endpoint `/up` + uptime monitor | 2 kegagalan berturut |
| Response time | APM (Sentry Performance / New Relic / Laravel Pulse) | p95 > 2× target selama 10 menit |
| PHP error | Sentry (PII scrubbing aktif) | Error baru, lonjakan |
| Failed jobs | Horizon + notifikasi | > 0 di queue `critical`/`pdf` |
| Queue backlog | Horizon metrics | Wait time > 5 menit |
| MySQL | Utilisasi CPU/IO, koneksi, replication lag | > 80% selama 15 menit |
| Slow query | `slow_query_log` (> 500 ms) | Query baru masuk top 10 |
| Storage | Kapasitas bucket dan disk | > 80% |
| Login gagal | Audit `auth.login_failed` | > 20/menit nasional atau > 10/jam per akun |
| Privileged access | Audit `privileged.*` | Setiap grant aktif → notifikasi Central Auditor |
| Export besar | Audit `export.*` | > 1.000 baris data sensitif |
| National search | Audit `identity.national_search` | > ambang per user |
| Backup | Status job backup | Gagal atau terlambat |

## 15.6 National rollout

| Wave | Cakupan | Fokus |
| --- | --- | --- |
| Pilot | Dammam + 2–4 kantor | Validasi workflow inti, transfer antar kantor pilot, umpan balik UI Arab |
| Wave 1 | 10–20 kantor | Onboarding terstandar, migrasi data historis |
| Wave 2 | 30–50 kantor tambahan | Skala support, performa |
| Wave 3 | Sisa kantor | Penyelesaian nasional |

Setiap wave: onboarding (aktivasi tenant, pembuatan Center Admin), pelatihan per role, migrasi data (import batch dengan duplicate check nasional), validasi data oleh kantor, periode hypercare 2 minggu, evaluasi dan go/no-go wave berikutnya.

Migrasi data historis: kantor menyerahkan spreadsheet sesuai template; import memvalidasi tiap baris; baris yang bertabrakan identitas dengan kantor lain masuk `duplicate_review_cases`, bukan ditimpa. Mualaf historis tanpa identitas resmi dimasukkan dengan identitas alternatif dan ditandai untuk verifikasi.
