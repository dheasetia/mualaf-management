# 01. Arsitektur

Satu aplikasi Laravel (modular monolith) yang di-scale horizontal di belakang load balancer. State hanya disimpan di MySQL, Redis, dan object storage sehingga setiap instance aplikasi stateless dan dapat ditambah tanpa koordinasi.

## 1.1 Deployment

```
Users ──HTTPS──> Load Balancer / Reverse Proxy (TLS, HSTS, rate limit kasar)
                        │
          ┌─────────────┼─────────────┐
          ▼             ▼             ▼
   Laravel app #1  Laravel app #2  Laravel app #N     (web + Filament, stateless)
          │             │             │
          ├─────────────┴─────────────┤
          │                           │
          ▼                           ▼
   Queue workers (Horizon)      Scheduler (1 instance, onOneServer)
          │                           │
          ├──────────────┬────────────┴───────────┐
          ▼              ▼                        ▼
   MySQL 8.4 primary   Redis                 Private object storage (S3-compatible)
     └─ read replica   - session (db 0)        - tenants/{uuid}/...
        (fase 9)       - cache (db 1)          - signed URL berumur pendek
                       - queue (db 2)
                       - rate limit (db 3)
   Chromium/Gotenberg (PDF rendering, internal network only)
```

| Komponen | Peran | Catatan |
| --- | --- | --- |
| Reverse proxy / LB | TLS termination, HSTS, rate limit kasar | Nginx atau managed LB |
| Laravel app | Web request + Filament | `SESSION_DRIVER=redis`, tanpa file lokal |
| Queue workers | Horizon, supervisor per queue | Lihat [13-infrastructure-security.md](13-infrastructure-security.md) |
| Scheduler | `schedule:run` di satu node | Reminder follow-up, expiry akses, agregasi statistik |
| MySQL 8.4 | Data utama | `utf8mb4_0900_ai_ci`, `sql_mode` strict, timezone UTC |
| Redis | Session, cache, queue, rate limiting | Index database terpisah per fungsi |
| Object storage | File, PDF, lampiran | Bucket private, server-side encryption |
| PDF renderer | HTML → PDF Arabic RTL | Browsershot (Chromium lokal) atau Gotenberg (service) |

Semua timestamp disimpan dalam UTC. Tampilan memakai timezone `Asia/Riyadh` dan dapat menampilkan tanggal Hijriah (lihat open question di [16-implementation-plan.md](16-implementation-plan.md)).

## 1.2 Struktur aplikasi

```
app/
├── Domain/
│   ├── Platform/          # master data: regions, cities, countries, languages, religions
│   ├── Tenancy/           # tenants, settings, memberships, TenantContext
│   ├── Identity/          # users, roles, permissions, MFA
│   ├── Audit/             # audit log, privileged access
│   ├── Prospects/
│   ├── Companies/         # direktori perusahaan nasional dan kantor (langkah 14)
│   ├── Converts/          # convert profile, identifiers, contacts, employment, residence
│   ├── Registration/      # registration, shahadah, duplicate review, origin correction
│   ├── Transfers/
│   ├── Dai/               # da'i, assignments, follow-ups, daily activities
│   ├── Dawah/             # plans, budget, materials, reports, participants
│   ├── Guidance/          # programs, modules, lessons, classes, enrollment, attendance
│   ├── Examinations/
│   ├── Certificates/
│   ├── Documents/         # documents, templates, generated documents
│   ├── Administration/    # submissions
│   ├── Transportation/
│   ├── Pilgrimage/
│   ├── FollowUp/          # long-term + international follow-up
│   └── Reporting/
├── Filament/
│   ├── Central/           # panel /central
│   └── Center/            # panel /center
├── Http/                  # controller publik (verifikasi sertifikat), middleware
└── Support/               # TenantContext, Fingerprint, NumberAllocator, Pdf, Redaction
```

Struktur internal setiap modul domain:

```
app/Domain/Transfers/
├── Actions/          # satu use case per class: RequestTransfer, ApproveTransfer, AcceptTransfer
├── Data/             # DTO input/output (spatie/laravel-data atau readonly class)
├── Enums/            # TransferStatus (backed enum) dengan transition map
├── Events/           # TransferApproved, TransferCompleted
├── Exceptions/       # TransferAlreadyActive, InvalidTransition
├── Models/           # ConvertTransferRequest, ConvertTransferEvent, ConvertTransferHistory
├── Policies/
└── Services/         # TransferStateMachine
```

### Aturan dependensi

1. Modul domain boleh memakai `Platform`, `Tenancy`, `Identity`, `Audit`, `Companies` (direktori bersama), dan `Support`.
2. Antar modul domain lain hanya melalui Actions dan Events publik; tidak ada query langsung ke tabel modul lain dari luar modul.
3. Filament Resource, Page, dan Livewire component hanya memanggil Actions; tidak berisi logika bisnis atau transisi status.
4. Perubahan status hanya melalui Service state machine; kolom `status` tidak masuk `$fillable`.
5. Aturan dependensi ditegakkan dengan arch test (Pest `arch()`), lihat [14-testing.md](14-testing.md).

## 1.3 Filament panels

| Panel | Path | Pengguna | Tenancy |
| --- | --- | --- | --- |
| Central | `/central` | Super Admin, Central Admin, Central Auditor, Central Reporting, Support Officer | Tanpa tenant; data individual sensitif hanya via privileged access |
| Center | `/center/{tenant:code}` | Semua role kantor | Filament tenancy; tenant dipilih dari membership aktif pengguna |

- `User::canAccessPanel()` memeriksa `is_active`, MFA terdaftar, dan ada role dengan scope yang sesuai panel.
- `User::getTenants()` mengembalikan tenant dari membership aktif saja; `canAccessTenant()` memverifikasi ulang di setiap request.
- URL tenant hanya *memilih* konteks. Middleware `SetTenantContext` mengisi `TenantContext` setelah verifikasi membership; model dan query membaca tenant dari `TenantContext`, tidak pernah dari input form.
- Filament diatur `->font('IBM Plex Sans Arabic')` (atau Noto Kufi Arabic), locale default `ar`, arah RTL otomatis dari locale.
- Dua bahasa antarmuka, Arab dan Inggris (`Locales`). Middleware `SetLocale` (grup `web` dan kedua panel) memakai `users.locale`, lalu pilihan sesi (halaman login), lalu `APP_LOCALE`. Pengguna mengganti bahasa dari menu pengguna atau tautan di halaman login (`POST /locale/{ar|en}`); pilihan disimpan di akun sehingga berlaku di perangkat lain. Nama master data, kantor, role, perusahaan, program, dan jenis pengajuan tampil lewat `LocalizedName` (`name_en` dengan cadangan `name_ar` bila kosong). Tetap berbahasa Arab: nama pribadi berbahasa Arab, sertifikat dan surat PDF (snapshot), dan kolom input `name_ar`.
- MFA wajib untuk semua role di panel Central dan untuk Center Admin, Head of Dawah, Approver (fitur MFA bawaan Filament: app authentication + recovery codes).

## 1.4 Konvensi kode

| Topik | Konvensi |
| --- | --- |
| Style | PSR-12 via Laravel Pint (preset `laravel`) |
| Static analysis | Larastan level 8 minimum, baseline tidak boleh bertambah |
| Strict types | `declare(strict_types=1);` di semua file `app/` |
| Enum | Backed string enum untuk semua status dan tipe; nilai snake_case Inggris |
| Label | Label UI di `lang/ar/*.php` dan `lang/en/*.php`; enum mengimplementasikan `HasLabel` Filament |
| Model | `final` bila tidak diturunkan; `$guarded = ['id', 'uuid', 'status', ...]` untuk kolom yang dikelola service |
| Route key | Model publik memakai `uuid` sebagai route key (`getRouteKeyName`) |
| Uang | `DECIMAL(14,2)`, mata uang SAR; cast ke `brick/money` atau string, bukan float |
| Tanggal | `immutable_datetime` cast; UTC di database |
| Commit | Subjek imperatif singkat, misal `Add transfer acceptance step` |
