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
| 7 | Da'i (selesai 22-11-2026) | 2 | `dais`, `dai_languages`, `dai_assignments` (skema), supervisor, status, workload view; dataset isolasi tenant + test kelengkapan |
| 8 | Prospect (selesai 22-11-2026) | 2 | `prospects` + histori append-only, status machine, `dai_follow_ups`, `DaiAssignmentService`, visibilitas `view_own`, deteksi telepon ganda |
| 9 | Identity registry (selesai 22-11-2026) | 2 | `convert_identifiers`, normalisasi + fingerprint, `IdentityRegistry` (penjaga unique, reveal diaudit), national search terbatas + rate limit; tabel inti `converts`, `ConvertScope`, `TenantContext::bypass` |
| 10 | Convert (selesai 22-11-2026) | 2 | Profil, kontak terenkripsi, disclosure level berlapis (`ConvertAccess`), status record + histori append-only, assign da'i; panel Center tanpa create |
| 11 | Shahadah (selesai 22-11-2026) | 2 | `number_sequences` + `NumberAllocator`, `RecordShahadah` (prospect atau walk-in, satu transaksi, penjaga identitas ganda), `VerifyShahadah` (segregation of duties), halaman registrasi + antrean verifikasi; saksi wajib (23-11-2026); kembalikan-untuk-perbaikan dan void dengan persetujuan Central (24-11-2026) |
| 12 | Duplicate detection (selesai 22-11-2026) | 2 | `NameSimilarity`, `DuplicateScreen` (nama + tanggal lahir + kewarganegaraan + gender, telepon), `duplicate_review_cases` + review Head of Dawah |
| 13 | Transfer (selesai 22-11-2026) | 2 | Transfer convert (ajukan dari pencarian nasional, review kantor lama, terima + selesai satu transaksi), transfer prospect, histori/event append-only, eskalasi terjadwal, follow-up convert + riwayat lintas kantor |
| 14 | Company / residence / contact (selesai 22-11-2026) | 3 | Direktori perusahaan (kantor + nasional, promosi/merge), employment, residence, contact person, lokasi dari tautan Google Maps |
| 15 | Guidance (selesai 22-11-2026) | 4 | Program nasional/kantor (salin), modul, lesson, kelas + jadwal + guru, enrollment (kapasitas, gender, kelanjutan transfer), sesi, absensi, progres, akses Teacher `converts.view_class` |
| 16 | Exams (selesai 22-11-2026) | 4 | Ujian berkomponen berbobot, attempt + remedial, penilaian (Teacher kelas sendiri), finalisasi immutable, amandemen beraudit |
| 17 | Certificates (selesai 22-11-2026) | 5 | Template berversi (placeholder ter-escape, bukan Blade), request/approve/issue/revoke, nomor + snapshot + token hash, PDF via Gotenberg, QR, verifikasi publik |
| 18 | Documents (selesai 22-11-2026) | 5 | Unggahan berversi (cek isi, hapus EXIF, link bertanda tangan 5 menit), verifikasi, surat bernomor dari template berversi dengan snapshot beku, template nasional di Central. Sisa: scan ClamAV (langkah 26) |
| 19 | Dawah planning (selesai 23-11-2026) | 6 | Aktivitas harian dengan verifikasi, plan dengan state machine penuh, peserta, laporan. Baris budget menyusul di langkah 20 |
| 20 | Budget / material (selesai 23-11-2026) | 6 | Baris budget (estimasi, approved, aktual, variance + catatan), syarat persetujuan plan; materi berversi dengan review, lampiran file, stok, distribusi. Kuitansi per baris (tindak lanjut). Materi nasional dari Central (24-11-2026) |
| 21 | Transport (selesai 23-11-2026) | 6 | Armada, transport kegiatan, pickup, trip tanpa tumpang tindih dan dalam kapasitas, lembar pickup tanpa data agama (critical test #14) |
| 22 | Submissions (selesai 23-11-2026) | 7 | Jenis (seed), workflow lengkap, lampiran per persyaratan milik submission, approval bertahap, penerbitan surat saat selesai. Sisa: pengelolaan jenis di Central, notifikasi SLA |
| 23 | Pilgrimage (selesai 23-11-2026) | 7 | Program, persyaratan, eligibility, registrasi, kuota terkunci + waitlist, grup. Sisa: consent (langkah 24), transport via kegiatan |
| 24 | Long-term follow-up (selesai 23-11-2026) | 8 | Status follow-up append-only, jadwal dari template + saran otomatis (PRD §31), catatan, negara asal, consent, referral internasional berbasis consent, checklist pra-kepulangan, direktori organisasi (Central). Sisa: notifikasi ke Primary Da'i |
| 25 | Reporting (selesai 23-11-2026) | 9 | Agregasi harian `reports:aggregate`, dashboard kantor (Center) dan nasional (Central) dari tabel agregat, export terenkripsi AES-256 24 jam. Sisa: import, read replica |
| 26b | Privileged access / break-glass (selesai 23-11-2026) | 10 | Permintaan, persetujuan four-eyes, grant berwaktu, tampilan baca-saja ber-scope dengan audit per pembukaan, kedaluwarsa otomatis |
| 26 | Security hardening (selesai 23-11-2026) | 10 | Header keamanan + CSP, log tanpa PII, scan ClamAV, notifikasi perangkat baru, review akses (halaman + `access:review`), sapuan IDOR otomatis, `LoadTestSeeder` + skrip k6, [runbook](../runbook.md). Sisa: pentest eksternal, eksekusi load test di staging, CSP tanpa `unsafe-eval`. Rotasi `APP_KEY`: `security:reencrypt` (tindak lanjut) |

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
| 1 | ~~Apakah sertifikat dan surat wajib menampilkan tanggal Hijriah (Umm al-Qura) di samping Masehi?~~ Diputuskan 23-11-2026: **wajib** di sertifikat. Sertifikat dua lembar: Arab dan Inggris (23-11-2026). | Template PDF, snapshot | Islamic Center Dammam |
| 2 | ~~Apakah syahadat wajib mencatat saksi (nama, kontak)?~~ Diputuskan 23-11-2026: **wajib** dicatat, minimal **dua** saksi (`SHAHADAH_MIN_WITNESSES=2`). | Tabel `shahadah_witnesses`, form registrasi | Kepala Bagian Dakwah |
| 3 | ~~Apakah gender convert membatasi da'i/guru yang dapat ditugaskan?~~ Diputuskan 23-11-2026: **tidak membatasi** penugasan da'i (sesuai implementasi; `gender_policy` kelas tetap mengatur komposisi kelas). | Validasi assignment, `guidance_classes.gender_policy` | Kepala Bagian Dakwah |
| 4 | SLA respons kantor lama untuk transfer sebelum eskalasi otomatis (usulan 7 hari)? | Scheduler eskalasi | Islamic Center Dammam |
| 5 | ~~Siapa yang menyetujui privileged access darurat bila Super Admin satu-satunya yang tersedia?~~ Diputuskan 22-11-2026: Central Auditor juga memegang `privileged.approve`; tanpa self-approval. | Break-glass policy | Security administration |
| 6 | Kebijakan retensi resmi per kategori data | `data_retention_rules` | Legal / kebijakan |
| 7 | ~~Hosting: cloud region di Saudi (misal penyedia lokal) atau on-premise?~~ Diputuskan 23-11-2026: **Hostinger**. Pusat data di luar Saudi diterima oleh pemilik (23-11-2026); penilaian PDPL §16.5 tetap mencatat transfer lintas negara ini. Perlu VPS (Gotenberg, ClamAV, Redis, worker, scheduler). | Infrastruktur, kepatuhan PDPL | Islamic Center Dammam |
| 8 | Apakah ada integrasi resmi dengan instansi pemerintah (Absher, Muqeem) di masa depan? | Integration logs, identitas terverifikasi | Islamic Center Dammam |
| 9 | ~~Kanal notifikasi selain email (SMS, WhatsApp Business API)?~~ Diputuskan 23-11-2026: **WhatsApp** (WhatsApp Business Platform / Cloud API: template pesan disetujui Meta, biaya per percakapan; isi tetap generik tanpa data pribadi). Diterapkan: kanal WhatsApp dengan opt-in per pengguna, mati sampai kredensial Meta dan template disetujui ([runbook §12](../runbook.md#12-whatsapp)). | Notification channels, biaya | Islamic Center Dammam |
| 10 | Ambang skor fuzzy duplicate (usulan 0,85) dan field yang dipakai — diterapkan sementara: 0,85 via `DUPLICATE_MATCH_THRESHOLD`, bobot di 04 §4.4 | Duplicate review volume | Tim teknis + admin pilot |

## 16.5 Kepatuhan

Platform memproses data pribadi sensitif (agama, identitas) di Saudi Arabia dan harus diselaraskan dengan Personal Data Protection Law (PDPL) dan pedoman SDAIA: dasar pemrosesan, minimisasi data, hak subjek data, pemberitahuan pelanggaran, dan transfer data lintas negara (relevan untuk referral internasional). Penilaian kepatuhan formal dilakukan oleh pihak yang berwenang sebelum pilot.
