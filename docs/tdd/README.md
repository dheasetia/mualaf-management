# Muallaf Center — Technical Design Document v1.1

| Atribut | Nilai |
| --- | --- |
| Versi | 1.1 (revisi dari v1.0 Final Technical Baseline, 20 November 2026) |
| Tanggal | 21 November 2026 |
| Dasar | PRD Muallaf Center National Platform v4.0 |
| Arsitektur | Laravel modular monolith, multi-tenant shared schema |
| Admin UI | Filament (panel `/central` dan `/center`) |
| Database | MySQL 8.4 (InnoDB, utf8mb4) |
| Cache / Queue | Redis (Laravel Horizon) |
| Bahasa UI | Arab (RTL), fallback Inggris |
| IDE | PhpStorm + Laravel Idea, Laravel Herd (lokal) |
| Pemilik | Islamic Center Dammam |
| Status | Draft untuk review Ready-to-Build Gate |

## Daftar isi

| File | Isi |
| --- | --- |
| [01-architecture.md](01-architecture.md) | Deployment, struktur modul, Filament panels, konvensi kode |
| [02-development-environment.md](02-development-environment.md) | Setup lokal Herd + PhpStorm, tooling, run configurations |
| [03-tenancy-authorization.md](03-tenancy-authorization.md) | Model tenancy, akses lintas tenant, RBAC, matriks permission |
| [04-identity-privacy.md](04-identity-privacy.md) | Identitas nasional, enkripsi, duplicate prevention, disclosure risk |
| [05-database-conventions.md](05-database-conventions.md) | Konvensi tabel, pola constraint MySQL, morph map, migrasi |
| [06-data-model-core.md](06-data-model-core.md) | Master data, tenant, user, RBAC, da'i, prospect, convert, profil |
| [07-data-model-lifecycle.md](07-data-model-lifecycle.md) | Registrasi, syahadat, duplicate review, transfer, assignment, follow-up da'i |
| [08-data-model-dawah.md](08-data-model-dawah.md) | Aktivitas harian, rencana dakwah, budget, materi, transportasi |
| [09-data-model-guidance.md](09-data-model-guidance.md) | Program pembinaan, kelas, absensi, ujian, sertifikat, dokumen |
| [10-data-model-administration.md](10-data-model-administration.md) | Submission, umrah/haji, follow-up jangka panjang dan internasional |
| [11-data-model-platform.md](11-data-model-platform.md) | Audit, penomoran, notifikasi, privileged access, import/export, retensi |
| [12-transactions-state-machines.md](12-transactions-state-machines.md) | Transaksi kritis dan state machine semua workflow |
| [13-infrastructure-security.md](13-infrastructure-security.md) | File storage, PDF, queue, cache, keamanan |
| [14-testing.md](14-testing.md) | Strategi test, critical tests, tenant isolation, concurrency |
| [15-operations.md](15-operations.md) | Performa, backup, environment, CI/CD, monitoring, rollout |
| [16-implementation-plan.md](16-implementation-plan.md) | Urutan implementasi, milestone, Ready-to-Build Gate, open questions |

## Perubahan dari v1.0

| # | Area | Perubahan | Alasan |
| --- | --- | --- | --- |
| 1 | Constraint | Pola *generated column + unique index* untuk semua aturan "satu aktif per X" | MySQL tidak mendukung partial/filtered unique index |
| 2 | Duplicate check | Unique index pada fingerprint menjadi penjaga utama; SELECT hanya untuk UX | SELECT-then-INSERT gagal pada registrasi bersamaan |
| 3 | Identifier | Fingerprint unik untuk semua identifier yang belum `replaced`, apa pun status verifikasinya | Celah duplikasi selama status `unverified` |
| 4 | Fingerprint | Normalisasi, HMAC key terpisah dari `APP_KEY`, `key_version` | Konsistensi pencocokan dan rotasi kunci |
| 5 | Primary Da'i | `primary_dai_id` = salinan denormalisasi, ditulis hanya oleh `DaiAssignmentService` | v1.0 punya dua sumber kebenaran |
| 6 | Transfer | Tahap baru `accepted` di kantor tujuan + penunjukan da'i baru | Da'i terikat tenant; v1.0 tidak menentukan da'i baru |
| 7 | Akses histori | Aturan *subject-scoped history* | Kantor aktif harus membaca histori dari kantor lama |
| 8 | Child tables | Scope via `converts.current_tenant_id` + test wajib | Tabel anak convert tidak punya `tenant_id` |
| 9 | Privasi | Hapus `converts.phone/email`; enkripsi kontak prospect | Data sensitif tersimpan plaintext |
| 10 | State machine | Lengkapi cabang prospect; pisahkan `converts.status` dari follow-up status | PRD §8, §31; critical test 9 |
| 11 | Disclosure | Matriks enforcement per level + break-glass | v1.0 hanya mendefinisikan kolom |
| 12 | Penamaan | `classes` → `guidance_classes` | `Class` adalah reserved word PHP |
| 13 | Tabel baru | `number_sequences`, `follow_up_schedule_templates`, `consent_records`, `prospect_transfer_requests`, `prospect_status_histories`, `dai_languages`, `shahadah_witnesses`, `class_schedules`, `exam_components`, `transport_trips`, `pilgrimage_registration_requirements`, `pre_departure_checklists` (+ items), `report_daily_tenant_stats`, `report_daily_national_stats` | Penomoran, jadwal configurable, consent sharing, transfer prospect, kebutuhan yang tersirat di PRD tetapi belum bertabel |
| 19 | Rename | `dai_assignments_to_people` → `dai_assignments`; `dawah_activity_plans.approval_status` digabung ke `status` | Penamaan ringkas; satu state machine per entitas |
| 14 | Notifications | Skema standar Laravel + `tenant_id` | Kompatibilitas framework |
| 15 | PDF | Browsershot (Chromium) atau Gotenberg | dompdf tidak men-shape huruf Arab dengan benar |
| 16 | Unique tambahan | `learning_progress(enrollment_id, lesson_id)` | Mencegah progress ganda |
| 17 | Index | Hapus index yang redundan dengan unique key | `certificates.certificate_number`, `attendances` |
| 18 | Append-only | Ditegakkan di level database (grant + trigger) | v1.0 hanya aturan aplikasi |

## Prinsip teknis final

1. Backend authorization bersifat otoritatif; UI hanya menyembunyikan.
2. Tenant ID dari client tidak pernah dipercaya; tenant context berasal dari membership terverifikasi.
3. Identitas sensitif dienkripsi; pencarian exact memakai HMAC fingerprint.
4. Pencegahan duplikasi bersifat nasional dan ditegakkan oleh unique index database.
5. Kantor asal immutable dalam CRUD biasa; hanya berubah via origin correction.
6. Kantor aktif hanya berubah via transfer yang disetujui dan diterima.
7. Prospect dan convert adalah entitas terpisah.
8. Transisi status hanya melalui domain service (state machine).
9. Histori kritis append-only dan ditegakkan di database.
10. Dokumen issued adalah snapshot immutable.
11. Queue job membawa tenant context eksplisit.
12. Akses lintas kantor eksplisit, terbatas waktu, dan diaudit.
13. Histori pembinaan mengikuti subjek (convert), bukan kantor.
14. Laravel modular monolith adalah baseline; scale horizontal sebelum mempertimbangkan microservices.
