# 04. Identitas, Duplicate Prevention, dan Privasi

Satu orang hanya boleh mempunyai satu record convert nasional (BR-001). Penjaga utamanya adalah unique index database pada fingerprint identitas; pencarian sebelum insert hanya untuk pengalaman pengguna.

## 4.1 Jenis identitas

| `identity_type` | Keterangan | Negara penerbit |
| --- | --- | --- |
| `national_id` | National ID Saudi | SA |
| `iqamah` | Resident ID ekspatriat | SA |
| `passport` | Paspor | Negara paspor |
| `gcc_id` | ID negara GCC | Negara penerbit |
| `border_number` | Nomor perbatasan (pengunjung) | SA |
| `other` | Identitas alternatif lain, wajib duplicate review manual | Bervariasi |

Satu convert dapat memiliki beberapa identifier (misal paspor lalu Iqamah). Tepat satu bertanda `is_primary`.

## 4.2 Normalisasi dan fingerprint

```
normalize(type, number):
  1. trim, hapus spasi, tanda hubung, titik, garis miring
  2. konversi digit Arab-Indic (٠١٢٣٤٥٦٧٨٩) dan Persia (۰-۹) ke ASCII
  3. uppercase (untuk paspor alfanumerik)
  4. untuk national_id/iqamah: validasi 10 digit, awalan 1 (warga) atau 2 (residen), checksum Luhn
fingerprint = HMAC-SHA256(IDENTITY_HMAC_KEY[v], type || '|' || issuing_country_iso2 || '|' || normalized)
```

- Disimpan sebagai `BINARY(32)` di kolom `identity_fingerprint`, beserta `fingerprint_key_version`.
- Nomor asli dienkripsi dengan Laravel `encrypted` cast (`APP_KEY`; rotasi via `APP_PREVIOUS_KEYS`) di `identity_number_encrypted`.
- Tampilan default memakai mask dari `identity_last4` tanpa dekripsi: `••••••4512`. Nomor penuh hanya dengan `identity.reveal` (`IdentityRegistry::reveal`) dan tercatat di audit (`identity.revealed`, via queue `audit`).
- Rotasi HMAC key: job `RehashIdentifiers` menghitung fingerprint versi baru ke kolom sementara, lalu swap; selama rotasi pencarian memeriksa kedua versi.

Implementasi (langkah 9, `app/Domain/Converts`): `IdentityNumber::normalize` (Luhn standar untuk national ID/Iqamah, paspor `[A-Z0-9]{5,20}`, GCC ID dan nomor perbatasan 6–15 digit, `other` `[A-Z0-9]{3,30}`); fingerprint = `HMAC(key, type|iso2|normalized)` lewat `App\Support\Identity\Fingerprint`. Untuk `national_id`, `iqamah`, `border_number` negara penerbit selalu SA (input negara diabaikan); jenis lain wajib memilih negara.

## 4.3 Aturan uniqueness

| Aturan | Implementasi |
| --- | --- |
| Satu fingerprint aktif nasional | Generated column `active_fingerprint = IF(replaced_at IS NULL, identity_fingerprint, NULL)` + `UNIQUE(identity_type, issuing_country_id, active_fingerprint)` |
| Berlaku sejak insert | Tidak bergantung pada `verification_status` (koreksi dari v1.0) |
| Satu primary per convert | Generated column `primary_key = IF(is_primary AND replaced_at IS NULL, convert_id, NULL)` + `UNIQUE(primary_key)` |

## 4.4 Alur duplicate check

1. **Pre-check (UX)**: Registration Officer memasukkan identitas → sistem menghitung fingerprint → `SELECT` pada `convert_identifiers`. Bila ada: tampilkan hasil terbatas (§3.4 di [03-tenancy-authorization.md](03-tenancy-authorization.md)) dan tombol *Ajukan Transfer*. Registrasi diblokir.
2. **Fuzzy check**: bila tidak ada exact match, cari kandidat berdasarkan nama ternormalisasi + tanggal lahir + kewarganegaraan (+ gender). Skor ≥ ambang (konfigurasi, default 0,85) → buat `duplicate_review_cases` dan registrasi menunggu keputusan `duplicates.review`.
3. **Insert (penjaga)**: dalam transaksi syahadat (lihat [12-transactions-state-machines.md](12-transactions-state-machines.md)), `IdentityRegistry::add` meng-insert identifier. Pelanggaran unique pada `uq_convert_identifiers_active_fingerprint` ditangkap dan diubah menjadi `ConvertAlreadyRegistered`; transaksi di-rollback. Audit kasus ini ditulis oleh pemanggil **setelah** rollback (audit di dalam transaksi ikut hilang).
4. Tidak memakai `SELECT ... FOR UPDATE` pada baris yang belum ada (gap lock → deadlock pada beban tinggi).

Implementasi fuzzy check (langkah 12, `Registration\Services\DuplicateScreen`, dijalankan `RecordShahadah` setelah pre-check identitas dan di luar transaksi):

- Kandidat nasional (bypass audit): convert dengan tanggal lahir sama, atau kewarganegaraan sama dan awalan 3 huruf nama pertama sama (maks. 200), tanpa convert `high_confidentiality`.
- Skor = rata-rata berbobot nama 0,60 (`NameSimilarity`: Levenshtein pada kata yang diurutkan dan cakupan per kata, terhadap `full_name`, `name_ar`, `previous_name`), tanggal lahir 0,25 (hanya bila keduanya ada), kewarganegaraan 0,10, gender 0,05. Kasus dibuka bila skor ≥ `DUPLICATE_MATCH_THRESHOLD` (default 0,85, open question #10) dan skor nama ≥ 0,75. Telepon yang sama dengan kontak aktif convert selalu membuka kasus (`phone_match`). Maks. 3 kasus per percobaan.
- Registrasi ditahan selama pasangan (kandidat, convert) punya kasus yang belum diputuskan `different_person`.

### Normalisasi nama untuk fuzzy match

- Hapus diakritik Arab (harakat, tatweel), samakan alif (أ إ آ → ا), ta marbuta (ة → ه), ya (ى → ي).
- Transliterasi nama Latin ke bentuk dasar lowercase tanpa spasi ganda.
- Simpan `name_search_key` (plaintext ternormalisasi, ber-index) di `converts`. Nama memang tidak dienkripsi karena dibutuhkan untuk pencarian; status agama terlindungi oleh akses tabel.

## 4.5 Klasifikasi data sensitif

| Data | Klasifikasi | Penyimpanan | Akses |
| --- | --- | --- | --- |
| Nomor identitas | Sangat sensitif | Encrypted + HMAC | `identity.reveal`, diaudit |
| Status agama, agama sebelumnya, syahadat | Sangat sensitif | Plaintext, akses tabel dibatasi | `converts.view_sensitive` |
| Kontak convert (telepon, email, WhatsApp) | Sensitif | `convert_contacts.value_encrypted` + `value_fingerprint` | `converts.view` + relasi |
| Kontak prospect | Sensitif | `prospects.phone_encrypted`, `email_encrypted` | `prospects.view`/`view_own` |
| Alamat dan koordinat tempat tinggal | Sensitif | Plaintext, akses dibatasi | `converts.view` + relasi |
| Informasi kesehatan (khitan) | Sangat sensitif | Hanya di dokumen/submission | `submissions.view` |
| Follow-up notes | Sensitif | Plaintext; `confidentiality_level` | `followups.view(_confidential)` |
| Dokumen | Sensitif | Object storage private | Signed URL ≤ 5 menit |

Audit log menyimpan nilai lama/baru dengan redaksi: field sensitif diganti `[REDACTED]` oleh `AuditRedactor` (`app/Domain/Audit/Services`). Yang diredaksi: daftar global (password, token, secret, recovery code), semua atribut `$hidden` model subjek, dan atribut dari `auditRedactedAttributes()` bila model mengimplementasikan `RedactsAuditValues`. Pencocokan key tidak peka huruf besar/kecil dan berlaku sampai array bersarang.

## 4.6 Disclosure risk

| Level | Efek |
| --- | --- |
| `normal` | Aturan RBAC standar |
| `restricted` | Hanya Primary Da'i, Head of Dawah, Center Admin, dan role dengan `converts.view_sensitive` yang melihat profil penuh; role lain (Teacher, Transport) hanya nama panggilan dan data operasional minimum; tidak tampil di export massal kecuali diizinkan |
| `high_confidentiality` | Hanya Primary Da'i dan Head of Dawah; nama ditampilkan sebagai alias di daftar; tidak boleh dicetak di daftar peserta/pickup tanpa persetujuan Head of Dawah; dikecualikan dari pencarian nasional berbasis nama (exact identity tetap memblokir duplikasi, hasil hanya "terdaftar di kantor lain, hubungi admin pusat") |

- Perubahan level memerlukan `converts.set_disclosure` dan alasan, tercatat di audit.
- Implementasi (langkah 10, `ConvertAccess`): profil penuh `restricted` untuk Primary Da'i dan pemegang `converts.view_restricted` (Head of Dawah, Center Admin), selainnya nama Islam atau inisial + status dan Primary Da'i; profil `high_confidentiality` untuk Primary Da'i dan pemegang `converts.view_high_confidentiality` (Head of Dawah), selainnya hanya alias `مهتدٍ XXXXXX` di daftar tanpa bisa dibuka dan tidak ikut pencarian nama. Agama sebelumnya hanya untuk pemegang `converts.view_sensitive` yang punya profil penuh, juga saat mengedit. Kedua permission `view_*` dan `converts.change_status` ditambahkan v1.1 agar aturan ini dinyatakan sebagai permission, bukan kode role.
- Sistem tidak pernah mengirim notifikasi masuk Islam secara otomatis ke keluarga, perusahaan, atau pihak lain. Kontak pihak ketiga hanya bila `permission_to_contact = true` dan tujuan kontak dicatat.
- Contact person dan home country contact mempunyai `permission_to_contact` dan `disclosure_scope` (`none`, `general_support`, `knows_conversion`).

## 4.7 Consent

Tabel `consent_records` (lihat [10-data-model-administration.md](10-data-model-administration.md)) mencatat persetujuan convert untuk: sharing ke organisasi luar negeri, kontak ke perusahaan/keluarga, penggunaan foto, dan partisipasi umrah/haji. Referral internasional tidak dapat berstatus `sent` tanpa consent aktif dengan scope yang sesuai.

## 4.8 Privileged access (break-glass)

Akses administrator pusat ke data individual hanya melalui `privileged_access_requests`:

1. Pemohon (role platform) mengajukan: resource, alasan, scope field, durasi (maks 24 jam).
2. Disetujui oleh pemegang `privileged.approve` (Super Admin atau Central Auditor) yang bukan pemohon (four-eyes). Tidak ada self-approval, termasuk dalam keadaan darurat; karena itu dua role memegang hak persetujuan.
3. Grant aktif disimpan di `TenantContext::privilegedGrantId`; policy memeriksa grant, scope, dan `expires_at`.
4. Setiap baca selama grant dicatat di audit dengan `privileged_grant_id`.
5. Scheduler mengakhiri grant kedaluwarsa setiap 5 menit.

Implementasi (`Privileged\Actions\PrivilegedAccess`, Central → **Akses istimewa**): sumber daya saat ini satu convert, dicari lewat nomor identitas (fingerprint nasional); scope `profile`, `contacts`, `identity`, `shahadah`, `follow_ups`; durasi 15–1440 menit. Grant aktif sejak disetujui (`expires_at` = persetujuan + durasi); penolakan wajib catatan; pemohon atau pemegang `privileged.approve` dapat mencabut. Hanya pemohon yang membuka halaman baca-saja grant aktif; setiap pembukaan dicatat `privileged.viewed` dengan `privileged_grant_id` dan `tenant_id` kantor convert (FK `audit_logs.privileged_grant_id` ditambahkan). Isi catatan follow-up tidak pernah ditampilkan, hanya jumlahnya. Penyetuju mendapat notifikasi saat ada permintaan, pemohon saat diputuskan. Grant tidak disimpan di `TenantContext`: data dibaca lewat konteks kantor convert hanya di dalam `PrivilegedConvertView`, sehingga tidak ada jalur lain yang ikut terbuka. `php artisan privileged:expire` tiap 5 menit.
