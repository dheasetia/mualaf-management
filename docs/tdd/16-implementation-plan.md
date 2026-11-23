# 16. Rencana Implementasi

## 16.1 Urutan implementasi

| # | Langkah | Fase PRD | Keluaran utama |
| --- | --- | --- | --- |
| 1 | Project foundation (selesai 21-11-2026) | 1 | Laravel + Filament, Pint, Larastan, Pest, CI, `.run/` PhpStorm, README |
| 2 | Tenancy (selesai 21-11-2026) | 1 | `tenants`, `tenant_settings`, `user_tenant_memberships`, `TenantContext`, `BelongsToTenant`, `SetTenantContext`, test isolasi dasar |
| 3 | Authentication (selesai 22-11-2026) | 1 | Login dua panel, MFA (wajib di Central; di Center menunggu role pada langkah 4), lockout per akun, session idle 30 menit + absolut 12 jam, tanpa remember-me |
| 4 | RBAC (selesai 22-11-2026, matriks menunggu persetujuan) | 1 | Tabel RBAC, `PermissionCatalog`/`RoleCatalog` + `RbacSeeder`, `Authorizer` + Gate, assign/revoke, MFA per role, `rbac:grant-super-admin` |
| 5 | Audit (selesai 22-11-2026) | 1 | `audit_logs`, `AuditLogger` + `AuditRedactor`, request id, append-only grant per tabel (`db:sync-app-grants`) + trigger; audit login, login gagal, MFA gagal, assign/revoke role; viewer read-only di Central (`audit.view_national`) dan Center (`audit.view_office`, hanya kantor sendiri) |
| 6 | Master data (selesai 22-11-2026) | 1 | Region, kota, negara, bahasa, agama, kategori, jenis aktivitas + Filament Central (`master.manage` menulis, user platform membaca) |
| 7 | Da'i | 2 | `dais`, `dai_languages`, supervisor, workload view |
| 8 | Prospect | 2 | `prospects`, status machine, follow-up da'i, assignment |
| 9 | Identity registry | 2 | `convert_identifiers`, fingerprint, national search terbatas |
| 10 | Convert | 2 | `converts`, kontak, disclosure level |
| 11 | Shahadah | 2 | Registrasi + syahadat + verifikasi |
| 12 | Duplicate detection | 2 | Fuzzy match, `duplicate_review_cases` |
| 13 | Transfer | 2 | Transfer convert dan prospect, histori, eskalasi |
| 14 | Company / residence / contact | 3 | Employment, residence, contact person, lokasi + Google Maps |
| 15 | Guidance | 4 | Program, modul, lesson, kelas, enrollment, sesi, absensi, progres |
| 16 | Exams | 4 | Ujian, komponen, attempt, hasil |
| 17 | Certificates | 5 | Template, issue, PDF, QR, verifikasi publik |
| 18 | Documents | 5 | Dokumen, versi, template surat, generated documents |
| 19 | Dawah planning | 6 | Aktivitas harian, plan, peserta, laporan |
| 20 | Budget / material | 6 | Budget, variance, materi, distribusi |
| 21 | Transport | 6 | Kendaraan, driver, trip, pickup |
| 22 | Submissions | 7 | Jenis, workflow, dokumen, approval |
| 23 | Pilgrimage | 7 | Program, eligibility, registrasi, grup |
| 24 | Long-term follow-up | 8 | Status follow-up, jadwal template, catatan, negara asal, referral internasional, consent |
| 25 | Reporting | 9 | Tabel agregat, dashboard kantor dan nasional, export |
| 26 | Security hardening | 10 | Pentest, CSP, review akses, load test, runbook |

## 16.2 Definition of Done per modul

- Memenuhi acceptance criteria PRD.
- Authorization: policy per aksi + test allowed/denied per role.
- Tenant isolation: model terdaftar di dataset isolasi dan lolos.
- Validation: Form Request / Filament rules + test validasi gagal.
- Automated tests: unit + feature, coverage modul ≥ 80%.
- Audit pada operasi kritis sesuai [11-data-model-platform.md](11-data-model-platform.md).
- UI Arab RTL lengkap (label `lang/ar`), diperiksa di layar desktop dan tablet.
- Tidak ada bug critical.
- Lolos UAT.
- Dokumentasi: TDD diperbarui bila desain berubah; README untuk perintah baru.

## 16.3 Ready-to-Build Gate

| Item | Status |
| --- | --- |
| PRD v4.0 disetujui | Menunggu konfirmasi |
| TDD v1.1 disetujui | Dalam review |
| Migration Phase 1–2 direview | Belum |
| Matriks permission disetujui | Draft di [03-tenancy-authorization.md](03-tenancy-authorization.md) |
| Workflow (state machine) disetujui | Draft di [12-transactions-state-machines.md](12-transactions-state-machines.md) |
| Klasifikasi privasi disetujui | Draft di [04-identity-privacy.md](04-identity-privacy.md) |
| Baseline infrastruktur disetujui | Draft di [01-architecture.md](01-architecture.md) dan [15-operations.md](15-operations.md) |

Langkah 1 (project foundation) dapat dimulai sebelum gate penuh karena tidak bergantung pada keputusan domain yang masih terbuka.

## 16.4 Open questions

| # | Pertanyaan | Dampak | Pemilik |
| --- | --- | --- | --- |
| 1 | Apakah sertifikat dan surat wajib menampilkan tanggal Hijriah (Umm al-Qura) di samping Masehi? | Template PDF, snapshot | Islamic Center Dammam |
| 2 | Apakah syahadat wajib mencatat saksi (nama, kontak)? | Tabel `shahadah_witnesses`, form registrasi | Kepala Bagian Dakwah |
| 3 | Apakah gender convert membatasi da'i/guru yang dapat ditugaskan? | Validasi assignment, `guidance_classes.gender_policy` | Kepala Bagian Dakwah |
| 4 | SLA respons kantor lama untuk transfer sebelum eskalasi otomatis (usulan 7 hari)? | Scheduler eskalasi | Islamic Center Dammam |
| 5 | ~~Siapa yang menyetujui privileged access darurat bila Super Admin satu-satunya yang tersedia?~~ Diputuskan 22-11-2026: Central Auditor juga memegang `privileged.approve`; tanpa self-approval. | Break-glass policy | Security administration |
| 6 | Kebijakan retensi resmi per kategori data | `data_retention_rules` | Legal / kebijakan |
| 7 | Hosting: cloud region di Saudi (misal penyedia lokal) atau on-premise? | Infrastruktur, kepatuhan PDPL | Islamic Center Dammam |
| 8 | Apakah ada integrasi resmi dengan instansi pemerintah (Absher, Muqeem) di masa depan? | Integration logs, identitas terverifikasi | Islamic Center Dammam |
| 9 | Kanal notifikasi selain email (SMS, WhatsApp Business API)? | Notification channels, biaya | Islamic Center Dammam |
| 10 | Ambang skor fuzzy duplicate (usulan 0,85) dan field yang dipakai | Duplicate review volume | Tim teknis + admin pilot |

## 16.5 Kepatuhan

Platform memproses data pribadi sensitif (agama, identitas) di Saudi Arabia dan harus diselaraskan dengan Personal Data Protection Law (PDPL) dan pedoman SDAIA: dasar pemrosesan, minimisasi data, hak subjek data, pemberitahuan pelanggaran, dan transfer data lintas negara (relevan untuk referral internasional). Penilaian kepatuhan formal dilakukan oleh pihak yang berwenang sebelum pilot.
