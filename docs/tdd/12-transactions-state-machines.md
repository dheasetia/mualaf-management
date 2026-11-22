# 12. Transaksi Kritis dan State Machine

Semua transisi status dijalankan oleh service state machine di modul domain. Setiap enum status mendefinisikan `allowedTransitions()`; service memvalidasi transisi, permission, dan prasyarat, lalu menulis event/histori dan audit dalam transaksi yang sama.

## 12.1 Pola implementasi

```php
enum TransferStatus: string
{
    case Pending = 'pending';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Accepted = 'accepted';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Escalated = 'escalated';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::UnderReview, self::Cancelled, self::Escalated],
            self::UnderReview => [self::Approved, self::Rejected, self::Escalated],
            self::Escalated => [self::Approved, self::Rejected, self::Cancelled],
            self::Approved => [self::Accepted, self::Cancelled],
            self::Accepted => [self::Completed],
            self::Completed, self::Rejected, self::Cancelled => [],
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Pending, self::UnderReview, self::Approved, self::Accepted, self::Escalated], true);
    }
}
```

Daftar nilai `isActive()` harus identik dengan ekspresi generated column `active_convert_key`; sebuah unit test membandingkan keduanya.

## 12.2 Prospect → Convert (registrasi syahadat)

Action: `Registration\Actions\RecordShahadah`. Prasyarat: prospect `ready_for_shahadah`, identitas sudah dimasukkan, pre-check tidak menemukan duplikasi, tidak ada `duplicate_review_cases` terbuka.

```
DB::transaction(attempts: 3):
  1. lock prospect (lockForUpdate); pastikan status = ready_for_shahadah dan tenant = context
  2. hitung fingerprint; cek ulang convert_identifiers (locking read pada baris yang ada)
  3. insert converts (origin_tenant_id = current_tenant_id = context.tenant, primary_dai_id = prospect.primary_dai_id)
  4. insert convert_identifiers  ← unique index menolak duplikasi bersamaan (catch 1062 → ConvertAlreadyRegistered)
  5. insert convert_contacts (dari kontak prospect, didekripsi lalu dienkripsi ulang)
  6. NumberAllocator → insert convert_registrations (status pending_verification)
  7. insert shahadah_records (record_type first, status recorded)
  8. update prospect: status converted, converted_at, convert_id; insert prospect_status_histories
  9. DaiAssignmentService: akhiri assignment prospect, buat assignment primary untuk convert (da'i sama)
 10. insert convert_status_histories (null → active)
 11. audit: converts.created, shahadah.recorded
commit
after commit: event ShahadahRecorded → notifikasi Shahadah Verifier
```

Verifikasi (`VerifyShahadah`, oleh user berbeda): status `verified` pada registration dan shahadah, identifier `verified`, lalu event `ShahadahVerified` → buat jadwal follow-up, enrollment default (opsional), dan permintaan sertifikat syahadat.

Walk-in tanpa prospect: action yang sama dengan langkah 1 dan 8 dilewati; Primary Da'i wajib dipilih pada form.

Implementasi (langkah 11, `app/Domain/Registration`):

- Modul Registration tidak memakai model Prospect atau Dai: langkah 1 dan 8 lewat `Prospects\Services\ProspectConversion` (`lockReady`, `markConverted`), langkah 3 lewat `Converts\Actions\CreateConvert` (satu-satunya pembuat convert), langkah 9 lewat `DaiAssignmentService::endAllFor` + `assignPrimary`. Arch test menjaga batas ini.
- Profil convert diambil dari form; bahasa ibu dan agama sebelumnya dilengkapi dari prospect bila kosong. Kontak telepon/email prospect (atau dari form untuk walk-in) menjadi kontak convert; telepon menjadi kontak primary.
- Tanggal syahadat tidak boleh di masa depan (zona Asia/Riyadh). Nomor registrasi `{KODE}-REG-{YYYY}-{000001}` per kantor dan tahun syahadat, dari `NumberAllocator` di dalam transaksi (rollback mengembalikan nomor, jadi tanpa celah).
- Identitas yang sudah aktif (pre-check atau unique key) → `ConvertAlreadyRegistered`; seluruh transaksi di-rollback, lalu audit `shahadah.duplicate_blocked` ditulis di luar transaksi dan form menampilkan galat pada field nomor identitas.
- Event `ShahadahRecorded`/`ShahadahVerified` dikirim setelah commit; listener (notifikasi verifier, jadwal follow-up, enrollment, permintaan sertifikat) dibuat pada langkah terkait.
- `VerifyShahadah` menolak user yang mendaftarkan atau mencatat syahadat (juga bila ia memegang `shahadah.verify`) dan registrasi yang tidak `pending_verification`; identifier convert ikut `verified`.
- Belum dibuat: penolakan dan void registrasi (butuh alur persetujuan Central, 07 §7.1) dan `shahadah_witnesses` (open question #2).

## 12.3 Transfer convert

Alur dan aktor:

```
Kantor tujuan                Kantor lama (aktif)            Central
─────────────                ───────────────────            ───────
cari identitas
ajukan transfer ──► pending
                             ──► under_review
                             approve ──► approved
                             (atau reject ──► rejected)
                                                            eskalasi bila tidak direspons
                                                            dalam SLA (default 7 hari)
                                                            ──► escalated ──► approved/rejected
terima + tunjuk da'i ──► accepted
sistem ──► completed (transaksi)
```

Transaksi penyelesaian (`CompleteTransfer`, dipanggil langsung oleh `AcceptTransfer`):

```
DB::transaction(attempts: 3):
  1. lock converts row
  2. lock convert_transfer_requests row
  3. pastikan request.status = accepted dan request.from_tenant_id = convert.current_tenant_id
  4. pastikan new_primary_dai_id aktif dan dai.tenant_id = request.to_tenant_id
  5. update converts.current_tenant_id = to_tenant_id (via TransferService, bukan mass assignment)
  6. insert convert_transfer_histories
  7. DaiAssignmentService: akhiri assignment kantor lama, assign primary da'i baru
  8. enrollment aktif kantor lama → transferred; follow-up schedules kantor lama → cancelled (reason transfer)
  9. update request: status completed, completed_at; insert convert_transfer_events
 10. audit transfer.completed
commit
after commit: notifikasi ke kedua kantor dan da'i baru; event TransferCompleted → jadwal follow-up template transfer_completed
```

Jaminan:

- Satu transfer aktif per convert: generated column `active_convert_key` (concurrency test: dua pengajuan simultan → satu gagal).
- Tidak dapat selesai dua kali: langkah 3 di bawah lock (critical test 3).
- `origin_tenant_id` tidak tersentuh.
- Kantor lama yang menolak wajib memberi alasan; kantor tujuan dapat mengajukan ulang setelah 30 hari atau eskalasi ke Central.

## 12.4 Penerbitan sertifikat

```
1. verifikasi kelayakan (lihat 09 §9.6) di luar transaksi untuk pesan UX
DB::transaction:
  2. lock baris certificate (status approved) dan subjek (enrollment/shahadah)
  3. cek ulang kelayakan dan tidak ada sertifikat issued aktif sejenis
  4. NumberAllocator → certificate_number
  5. bangun snapshot_data (nama ar/en, tanggal syahadat Masehi/Hijriah, kantor, penandatangan, nomor)
  6. token = random 32 byte; simpan SHA-256(token) ke verification_token_hash
  7. status issued, issued_at, issued_by; certificate_events; audit
commit
after commit: job GenerateCertificatePdf(certificate_id, tenant context, token) di queue pdf
  - render HTML template versi terkait + snapshot + QR → PDF
  - simpan ke tenants/{uuid}/certificates/{certificate_uuid}.pdf, checksum
  - token tidak disimpan plaintext; QR hanya ada di PDF
```

Bila job PDF gagal, sertifikat tetap `issued` dengan `file_document_id NULL` dan tampil sebagai "PDF sedang diproses"; job di-retry dan kegagalan permanen memicu alert. Regenerasi PDF memakai snapshot yang sama dan token baru (token lama dicabut) hanya oleh Certificate Officer dengan audit.

Pencabutan: status `revoked`, alasan wajib, verifikasi publik menampilkan "dicabut". Penggantian: sertifikat baru, lama `replaced` dengan `replaced_by_certificate_id`.

## 12.5 State machines

### Prospect

| Dari | Ke |
| --- | --- |
| new | contacted, not_interested, lost_contact, closed |
| contacted | active_follow_up, not_interested, lost_contact, closed |
| active_follow_up | learning_islam, considering_islam, not_interested, lost_contact, transferred, closed |
| learning_islam | considering_islam, active_follow_up, not_interested, lost_contact, transferred, closed |
| considering_islam | ready_for_shahadah, learning_islam, not_interested, lost_contact, transferred, closed |
| ready_for_shahadah | converted (hanya via RecordShahadah), considering_islam, lost_contact |
| not_interested | active_follow_up, closed |
| lost_contact | contacted, closed |
| transferred, converted, closed | — (terminal) |

### Convert record

| Dari | Ke | Catatan |
| --- | --- | --- |
| active | inactive, deceased, record_closed | Head of Dawah + alasan |
| inactive | active, deceased, record_closed | |
| deceased, record_closed | — | koreksi hanya via Central |

Status follow-up (`convert_follow_up_statuses`) bebas berpindah antar nilai oleh da'i/Head of Dawah karena merupakan catatan kondisi, bukan state machine ketat.

### Transfer convert

`pending → under_review → approved → accepted → completed`; cabang `rejected`, `cancelled` (oleh pengaju sebelum accepted), `escalated` (Central).

### Transfer prospect

`pending → approved → completed`; cabang `rejected`, `cancelled`.

### Enrollment

`enrolled → active → completed`; cabang `paused ↔ active`, `withdrawn`, `transferred`.

### Ujian

`scheduled → in_progress → graded → pending_finalization → finalized`; cabang `cancelled` sebelum graded. Hasil: `provisional → finalized → amended`.

### Sertifikat

`draft → pending_approval → approved → issued`; cabang `rejected` (dari pending_approval), `revoked` dan `replaced` (dari issued).

### Submission

`draft → submitted → under_verification → approved → processing → completed`; cabang `needs_correction` (dari under_verification, kembali ke submitted), `rejected` (dari under_verification/approval), `cancelled` (sebelum processing).

### Rencana dakwah

`draft → submitted → supervisor_review → approved → scheduled → in_progress → completed → report_submitted → verified`; cabang `needs_revision` (dari supervisor_review, kembali ke submitted), `rejected`, `cancelled` (sebelum in_progress).

### Registrasi umrah/haji

`applied → eligibility_checked → eligible → approved → confirmed → departed → completed`; cabang `ineligible`, `waitlisted`, `withdrawn`.

### Referral internasional

`draft → pending_consent → ready → sent → acknowledged → active → closed`; cabang `declined` (organisasi menolak), `closed` (consent ditarik).

## 12.6 Event domain utama

| Event | Listener |
| --- | --- |
| `ShahadahRecorded` | Notifikasi verifier |
| `ShahadahVerified` | Jadwal follow-up, permintaan sertifikat syahadat, statistik |
| `TransferRequested` | Notifikasi kantor lama (Head of Dawah) |
| `TransferApproved` | Notifikasi kantor tujuan |
| `TransferCompleted` | Notifikasi kedua kantor dan da'i baru, jadwal follow-up |
| `EnrollmentCompleted` | Permintaan sertifikat program, jadwal follow-up |
| `ConvertReturnedHome` | Checklist kepulangan, jadwal follow-up internasional |
| `FollowUpOverdue` (scheduler) | Notifikasi da'i, eskalasi ke Head of Dawah setelah 7 hari |
| `ConsentWithdrawn` | Tutup referral terkait |

Listener yang menulis data berjalan *after commit* (`ShouldHandleEventsAfterCommit`) dan di queue dengan tenant context.
