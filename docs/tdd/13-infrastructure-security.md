# 13. Infrastruktur dan Keamanan

## 13.1 File storage

- Disk `private` (S3-compatible, bucket private, server-side encryption). Disk lokal `local-private` untuk development.
- Struktur logis:

```
tenants/{tenant_uuid}/
  converts/{convert_uuid}/documents/{document_uuid}/v{n}.{ext}
  prospects/{prospect_uuid}/...
  certificates/{certificate_uuid}.pdf
  generated/{generated_document_uuid}.pdf
  submissions/{submission_uuid}/...
  dawah/{plan_uuid}/...
  pilgrimage/{program_uuid}/...
exports/{export_uuid}.zip        -- dihapus otomatis setelah expires_at
imports/{batch_uuid}/source.xlsx
```

- File convert disimpan di folder tenant *pemilik saat upload*; path disimpan di `documents.storage_path`, sehingga setelah transfer file tidak perlu dipindah dan akses tetap melalui policy convert.
- Download hanya lewat controller yang memanggil policy lalu redirect ke `temporaryUrl()` berumur 5 menit; event `document.downloaded` diaudit.
- Upload: whitelist MIME (`application/pdf`, `image/jpeg`, `image/png`, `image/webp`), maksimal 10 MB, validasi isi (bukan hanya ekstensi), nama file diganti UUID, scan antivirus (ClamAV) via job sebelum `verification_status` dapat diubah.
  - Status langkah 18: tipe dibaca dari isi file (`finfo`), batas 10 MB, nama disimpan sebagai `v{n}.{ext}` di folder UUID, gambar di-encode ulang dengan GD (EXIF/GPS hilang, orientasi diterapkan dulu), unduhan lewat `temporaryUrl()` 5 menit yang diaudit (`document.downloaded`; di disk lokal berupa signed URL `storage.local`). Scan ClamAV **belum**: dijadwalkan di langkah 26, sampai saat itu verifikasi tidak menunggu scan.
- Gambar di-strip metadata EXIF (lokasi GPS) saat upload.

## 13.2 Pembuatan dokumen PDF

| Kebutuhan | Solusi |
| --- | --- |
| Arabic shaping + RTL | Chromium (Browsershot) atau Gotenberg; bukan dompdf |
| Font | IBM Plex Sans Arabic / Amiri / Noto Naskh Arabic, di-bundle lokal (bukan CDN) |
| Ukuran | A4, margin dikonfigurasi per template |
| QR | SVG inline dari `chillerlan/php-qrcode` |
| Tanda tangan dan stempel | Gambar per tenant + penandatangan, disimpan sebagai `documents`, disisipkan saat render |
| Versioning | Template ber-versi; render memakai versi yang terekam di record |
| Snapshot | Render hanya dari `snapshot_data`, tidak dari tabel live |
| Tanggal | Masehi dan Hijriah (Umm al-Qura) via `IntlDateFormatter` `ar_SA@calendar=islamic-umalqura` |

Template HTML diisi oleh `TemplateRenderer` (langkah 17): hanya placeholder `{{ field }}` dari snapshot yang diganti dan di-escape, `{{ qr_code }}` satu-satunya sisipan mentah; tidak ada kode template yang dieksekusi (pengganti "Blade sandbox"). Renderer: Gotenberg 8 (`GOTENBERG_URL`). Font Arab belum di-bundle; sementara memakai font sistem Gotenberg. Template dibuat/diubah hanya oleh `documents.templates.manage` dan butuh approval sebelum aktif.

## 13.3 Queue

| Queue | Job | Workers | Timeout |
| --- | --- | --- | --- |
| `critical` | Notifikasi transfer/syahadat, audit async | 2 | 60 s |
| `pdf` | Sertifikat, surat | 2 | 180 s |
| `default` | Notifikasi umum, email | 2 | 60 s |
| `exports` | Export | 1 | 900 s |
| `imports` | Import, validasi baris | 1 | 900 s |
| `reports` | Agregasi statistik | 1 | 1800 s |
| `files` | Antivirus, thumbnail, EXIF strip | 1 | 300 s |

Aturan:

- Setiap job tenant-scoped mengimplementasikan `TenantAware` dan memakai middleware `RestoreTenantContext`; job gagal cepat bila context hilang.
- Job hanya membawa ID dan UUID, tidak membawa data sensitif di payload.
- Job idempotent (cek status sebelum aksi) karena dapat di-retry.
- `failed_jobs` dipantau; payload di-redaksi.

## 13.4 Cache

- Redis; prefix `muallaf:{env}:`.
- Kunci data tenant: `tenant:{tenant_id}:...`. Kunci global hanya untuk master data (`master:countries`).
- Tidak meng-cache hasil authorization secara global; permission cache per user per tenant dengan TTL pendek dan invalidasi eksplisit.
- Tidak meng-cache data sensitif individual (identitas, kontak, status agama).
- Rate limit: login 5/menit/IP + 10/jam/akun; national search 30/jam/user; verifikasi sertifikat 60/menit/IP; export 5/jam/user.

## 13.5 Keamanan aplikasi

| Kontrol | Implementasi |
| --- | --- |
| HTTPS | Wajib, HSTS 1 tahun, cookie `Secure`, `HttpOnly`, `SameSite=Lax` |
| Autentikasi | Filament login; password minimal 12 karakter, cek kebocoran (`Password::uncompromised()`) |
| MFA | Wajib untuk role platform dan role kantor yang punya approve/verify/reveal |
| Session | Redis, idle timeout 30 menit (`SESSION_LIFETIME`), absolute 12 jam (`SESSION_ABSOLUTE_LIFETIME`, middleware `EnforceAbsoluteSessionLifetime`), regenerasi saat login, sesi lain berakhir saat password berubah (`AuthenticateSession`); opsi "remember me" dihapus karena akan melewati idle timeout |
| Lockout | 5 gagal → lock 15 menit per email (`AccountLockout`), berlaku juga untuk email yang tidak terdaftar agar tidak membuka keberadaan akun; notifikasi email ke pemilik akun sekali saat terkunci |
| CSRF | Bawaan Laravel/Livewire |
| XSS | Blade escaping; rich text disanitasi (HTML Purifier) sebelum simpan |
| SQL injection | Query builder/Eloquent saja; raw query wajib binding dan review |
| Mass assignment | `$fillable` eksplisit; kolom tenant, status, origin, generated di `$guarded` |
| Headers | CSP ketat (self + aset Filament), `X-Frame-Options: DENY`, `Referrer-Policy: same-origin`, `Permissions-Policy` |
| Secrets | Secret manager di staging/produksi; `.env` tidak di-commit; `APP_KEY` dan `IDENTITY_HMAC_KEY` terpisah dan dirotasi terjadwal |
| Database | User aplikasi tanpa DDL dan tanpa UPDATE/DELETE pada tabel append-only; TLS ke MySQL |
| Backup | Terenkripsi; kunci terpisah dari storage backup |
| Dependency | `composer audit` dan `npm audit` di CI; Dependabot/Renovate |
| Logging | Log aplikasi tanpa PII (processor Monolog meredaksi email, telepon, nomor identitas) |
| Error | `APP_DEBUG=false` di non-lokal; error page generik |
| Penetration test | Sebelum pilot dan sebelum setiap wave rollout; fokus tenant isolation dan IDOR |

Status implementasi (langkah 26):

- Header: middleware `SecurityHeaders` di grup web. CSP hanya mengizinkan origin sendiri (+ host font Filament `CSP_FONT_HOST`, + Vite dev server di lokal), `frame-ancestors 'none'`, `object-src 'none'`, `base-uri`/`form-action 'self'`; **masih** `'unsafe-inline' 'unsafe-eval'` pada `script-src` karena Livewire/Alpine — target berikutnya build Alpine CSP + nonce. HSTS 1 tahun hanya lewat HTTPS. Font Filament belum di-bundle lokal.
- Logging: `tap` `RedactPersonalData` pada channel `single`/`daily` memasking email, nomor telepon, dan nomor identitas 10 digit pada pesan dan context.
- Antivirus: `ScanDocument` (antrean `files`) memanggil clamd via INSTREAM; `documents.scan_status` (`pending/clean/infected/failed/skipped`); hanya file `clean` (atau `skipped` bila `CLAMAV_ENABLED=false`) yang dapat diverifikasi (dokumen convert, dokumen submission, persyaratan umrah) atau diunduh; file terinfeksi dihapus dan dokumen ditolak; clamd tak terjangkau → dicoba ulang, file tetap tidak dapat dipakai.
- Login dari browser baru (hash HMAC user agent, `user_login_devices`) dalam `KNOWN_DEVICE_DAYS` memicu email dan audit `auth.new_device`; login pertama tidak dilaporkan.
- Laporan akses berkala: halaman **Review akses** (`audit.view_office`) dan `php artisan access:review` bulanan ke audit log (role sensitif tanpa authenticator, akun tidak dipakai `STALE_ACCOUNT_DAYS`).
- Pentest: belum dilakukan pihak eksternal; sebagai gantinya `tests/TenantIsolation/IdorSweepTest.php` membuka setiap halaman record Center dengan record kantor lain oleh pengguna berperan luas (semua harus gagal). Checklist pra-pilot di [runbook](../runbook.md) §11.

## 13.6 Ancaman utama dan mitigasi

| Ancaman | Mitigasi |
| --- | --- |
| Kebocoran lintas tenant (IDOR) | UUID di URL, global scope, policy per record, test isolasi untuk setiap resource |
| Enumerasi identitas via national search | Rate limit, audit, hasil terbatas, alert bila > ambang |
| Insider membaca data mualaf tanpa keperluan | Least privilege, disclosure level, audit view sensitif, laporan akses berkala ke Local Auditor |
| Pengungkapan status agama ke pihak ketiga | Tidak ada notifikasi otomatis ke pihak ketiga, `permission_to_contact`, lembar pickup tanpa data agama |
| Sertifikat palsu | QR token hash, halaman verifikasi resmi, status revoked |
| Pengambilalihan akun admin | MFA, lockout, notifikasi login baru, sesi pendek |
| Kehilangan data | Backup terenkripsi, binlog, uji restore berkala |
