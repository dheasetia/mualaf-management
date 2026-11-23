# 09. Data Model — Pembinaan, Kelas, Ujian, Sertifikat, Dokumen

Notasi sama dengan [06-data-model-core.md](06-data-model-core.md).

## 9.1 Program pembinaan

```
guidance_programs
  id pk · tenant_id? fk          -- NULL = program nasional (template dari Dammam)
  code · name_ar · name_en? · description?
  duration_days int default 90
  minimum_attendance_percentage decimal(5,2) default 75.00
  passing_score decimal(5,2) default 60.00
  certificate_template_id? fk → certificate_templates
  based_on_program_id? fk → guidance_programs
  active bool
  uq(tenant_id, code)

guidance_modules
  id pk · program_id fk · title · description? · sort_order int · weight decimal(5,2) · required bool

guidance_lessons
  id pk · module_id fk · title · description? · learning_objectives? text · sort_order int
```

Program nasional default (PRD §19): aqidah, syahadat, thaharah, wudhu, shalat, Al-Qur'an, doa, ibadah, akhlak, muamalah dasar. Kantor dapat menyalin program nasional menjadi program kantor (`based_on_program_id`).

## 9.2 Kelas

```
guidance_classes                -- pengganti "classes" (reserved word PHP)
  id pk · uuid uq · tenant_id fk · program_id fk → guidance_programs
  code · name · language_id fk → languages · gender_policy enum(male, female, mixed)
  capacity int · room? · location?
  starts_on date · ends_on date
  status enum(planned, open, in_progress, completed, cancelled)
  uq(tenant_id, code)

class_schedules
  id pk · class_id fk · day_of_week tinyint · start_time · end_time   -- pola mingguan

class_teacher_assignments
  id pk · class_id fk · user_id fk → users
  role enum(lead_teacher, assistant, substitute) · assigned_at · released_at?
```

## 9.3 Enrollment

```
enrollments
  id pk · uuid uq · convert_id fk
  origin_tenant_id fk → tenants      -- salinan untuk statistik
  service_tenant_id fk → tenants     -- kantor yang menyelenggarakan
  program_id fk · class_id? fk → guidance_classes
  enrolled_at · target_completion_date date
  status enum(enrolled, active, paused, completed, withdrawn, transferred)
  completed_at? · withdrawn_reason? · transferred_to_enrollment_id? fk → enrollments
  attendance_percentage? decimal(5,2)  -- cache, dihitung job
  final_score? decimal(5,2)
  active_enrollment_key gen uq         -- IF(status IN ('enrolled','active','paused'), CONCAT(convert_id,':',program_id), NULL)
  idx(service_tenant_id, status) · idx(class_id, status)
```

Kapasitas kelas: `EnrollmentService` mengunci baris `guidance_classes` (`lockForUpdate`) lalu menghitung enrollment aktif sebelum insert (concurrency test wajib).

Saat transfer convert, enrollment aktif di kantor lama menjadi `transferred`; kantor baru membuat enrollment baru yang merujuk enrollment lama, dan progres lesson yang sudah selesai disalin sebagai `learning_progress` dengan `source = transferred`.

## 9.4 Sesi, absensi, progres

```
class_sessions
  id pk · tenant_id fk · class_id fk · lesson_id? fk → guidance_lessons
  teacher_user_id fk → users · starts_at · ends_at
  status enum(scheduled, held, cancelled) · notes?
  idx(class_id, starts_at)

attendances
  id pk · tenant_id fk · class_session_id fk · enrollment_id fk
  status enum(present, absent, excused, late)
  recorded_by fk → users · recorded_at · notes?
  uq(class_session_id, enrollment_id)

learning_progress
  id pk · tenant_id fk · enrollment_id fk · lesson_id fk
  progress_status enum(not_started, in_progress, completed, needs_support)
  score? decimal(5,2) · competency_notes? · needs_mentoring bool default false
  assessed_by? fk → users · assessed_at?
  source enum(direct, transferred) default 'direct'
  notes?
  uq(enrollment_id, lesson_id)
```

Persentase kehadiran = `(present + late) / (sesi held − excused) × 100`. Bobot `late` dapat dikonfigurasi (default 1,0).

Implementasi (langkah 15, `app/Domain/Guidance`):

- Program: nasional (`tenant_id NULL`, template; `GuidanceProgramSeeder` membuat `NEW-MUSLIM` dengan 10 modul PRD §19) atau milik kantor; kantor melihat keduanya (`ProgramScope`) dan hanya mengubah miliknya (`guidance.programs.manage`), program nasional disalin (`copyNational`) beserta modul dan pelajaran. Kode nasional unik lewat generated column `national_code_key`. Pelajaran yang sudah punya progres tidak dapat dihapus.
- Kelas (`guidance.classes.manage`): status `planned → open → in_progress → completed`, `cancelled` dari planned/open; hanya `open`/`in_progress` menerima enrollment. Guru harus anggota aktif kantor; satu penugasan aktif per guru per kelas.
- Enrollment (`guidance.enroll`): convert aktif kantor ini; kelas harus milik program yang sama; `gender_policy` ditegakkan (open question #3: diterapkan sesuai kolom); kapasitas dihitung pada baris kelas yang dikunci. `completed` ditolak bila kehadiran di bawah `minimum_attendance_percentage`; `withdrawn` wajib alasan; `transferred` hanya oleh listener `TransferCompleted`. Enrollment baru untuk program yang sama setelah transfer menautkan enrollment lama dan menyalin pelajaran `completed` (`source = transferred`).
- Sesi, absensi, progres: absensi dan progres hanya oleh guru yang **saat ini** ditugaskan ke kelas itu (`guidance.attendance.record`/`guidance.progress.record`; critical test #6); sesi oleh koordinator atau guru kelas. Absensi menandai sesi `held`, mengaktifkan enrollment `enrolled`, dan menghitung ulang `attendance_percentage` secara sinkron (bukan job).
- Guru (pemegang perekam absensi/progres tanpa `guidance.classes.manage`) hanya melihat kelasnya; `converts.view_class` melihat convert yang terdaftar di kelasnya lewat kontrak `Converts\Contracts\TeacherRoster` (diimplementasikan Guidance).

## 9.5 Ujian

```
exams
  id pk · tenant_id fk · program_id fk · class_id? fk
  title · exam_type enum(evaluation, practical, monthly, final, remedial)
  maximum_score decimal(6,2) · passing_score decimal(6,2)
  scheduled_at? · status enum(scheduled, in_progress, graded, pending_finalization, finalized, cancelled)

exam_components
  id pk · exam_id fk · component_code · name · max_score decimal(6,2) · weight decimal(5,2)

exam_attempts
  id pk · tenant_id fk · exam_id fk · enrollment_id fk · examiner_user_id fk → users
  attempt_number tinyint · attempted_at?
  status enum(scheduled, absent, in_progress, graded, finalized)
  uq(exam_id, enrollment_id, attempt_number)

exam_scores
  id pk · exam_attempt_id fk · exam_component_id fk · score decimal(6,2) · notes?
  uq(exam_attempt_id, exam_component_id)

exam_results
  id pk · tenant_id fk · exam_attempt_id fk uq
  final_score decimal(6,2) · passed bool
  status enum(provisional, finalized, amended)
  finalized_by? fk → users · finalized_at? · amendment_reason?
```

Hasil `finalized` immutable; perubahan memakai status `amended` dengan alasan dan audit. Teacher hanya dapat menilai ujian untuk kelas yang ditugaskan; finalisasi oleh Examination Officer.

Implementasi (langkah 16, `app/Domain/Examinations`; memakai Guidance hanya lewat `EnrollmentDirectory` dan `TeacherAssignments`, dijaga arch test):

- `exams.manage`: ujian dengan minimal satu komponen (`passing_score ≤ maximum_score`; kelas harus milik program ujian), hanya dapat diubah selama `scheduled`; kandidat = enrollment program (dan kelas) tanpa attempt terbuka dan belum lulus pada hasil final; nomor attempt berurutan.
- Nilai akhir = Σ(skor/max × bobot) / Σ bobot × `maximum_score` (`ScoreCalculator`), lulus bila ≥ `passing_score`. `exams.grade` mengisi semua komponen dalam batasnya; Teacher hanya untuk kandidat kelas yang ia ajar. Ujian otomatis `graded` bila tidak ada attempt yang tersisa.
- `graded → pending_finalization` (`exams.grade`), `pending_finalization → finalized` atau kembali ke `graded` (`exams.finalize`). Finalisasi membuat hasil `finalized` (model menolak perubahan berikutnya), attempt `finalized`, dan menyalin nilai final terbaik ke `enrollments.final_score`. Amandemen: `exams.finalize`, alasan wajib, audit `exams.result_amended`.
- Teacher (pemegang `exams.grade` tanpa `exams.view`/`exams.manage`) hanya melihat ujian kelasnya.

## 9.6 Sertifikat

```
certificate_templates
  id pk · tenant_id? fk · template_code
  certificate_type enum(shahadah, guidance_completion)
  name · template_version int · html_template longtext · configuration json
  paper_size enum(A4) · orientation enum(portrait, landscape)
  active bool · approved_by? fk → users · approved_at?
  uq(tenant_id, template_code, template_version)

certificates
  id pk · uuid uq · tenant_id fk      -- kantor penerbit
  convert_id fk · enrollment_id? fk
  certificate_template_id fk          -- versi template yang dipakai
  certificate_type enum(shahadah, guidance_completion)
  certificate_number varchar(40) uq   -- {TENANT}-{TYPE}-{YYYY}-{000001}
  verification_token_hash binary(32) uq
  snapshot_data json                  -- data yang dicetak, beku saat issue
  issued_at? · issued_by? fk → users · approved_by? fk → users
  file_document_id? fk → documents · file_checksum? char(64)
  status enum(draft, pending_approval, approved, issued, rejected, revoked, replaced)
  revoked_at? · revoked_by? · revocation_reason?
  replaced_by_certificate_id? fk → certificates

certificate_events               -- append-only
  id pk · certificate_id fk · actor_id fk · event_type · metadata json · created_at
```

Kelayakan:

| Jenis | Syarat |
| --- | --- |
| شهادة إشهار الإسلام | `shahadah_records` status `verified`; belum ada sertifikat syahadat `issued` aktif |
| شهادة إتمام برنامج التأهيل | Enrollment `completed`; kehadiran ≥ minimum program; ujian final `finalized` dan lulus |

Verifikasi publik: QR berisi URL `https://<domain>/verify/{certificate_uuid}?t={token}`. Server menghitung `SHA-256(token)` dan membandingkan dengan `verification_token_hash`. Halaman verifikasi hanya menampilkan: nomor sertifikat, jenis, tanggal terbit, kantor penerbit, status (valid/dicabut/diganti), dan nama (inisial bila disclosure ≠ normal). Endpoint di-rate-limit 60/menit/IP.

## 9.7 Dokumen

```
documents
  id pk · uuid uq · tenant_id? fk
  owner_type · owner_id                -- morph map
  document_type varchar(60)            -- iqamah_copy, photo, medical_letter, receipt, ...
  original_filename · storage_path · mime_type · file_size bigint · checksum char(64)
  current_version int default 1
  verification_status enum(pending, verified, rejected)
  uploaded_by fk → users · verified_by? · verified_at?
  idx(owner_type, owner_id)

document_versions
  id pk · document_id fk · version_no int · storage_path · checksum · uploaded_by fk · created_at
  uq(document_id, version_no)

document_templates
  id pk · tenant_id? fk · template_code
  document_type enum(shahadah_certificate, new_convert_data_letter, khitan_referral_letter,
                     religion_update_letter, guidance_certificate, other_letter)
  name_ar · description? · html_template longtext
  paper_size · orientation · version int · is_global bool
  active bool · approved_by? · approved_at?
  uq(tenant_id, template_code, version)

generated_documents
  id pk · uuid uq · tenant_id fk · template_id fk → document_templates
  subject_type · subject_id
  document_number varchar(40) uq
  snapshot_data json · file_document_id? fk → documents · file_checksum?
  status enum(draft, pending_approval, issued, cancelled)
  generated_by fk → users · approved_by? · issued_at?
```

Aturan:

- Template baru = versi baru; dokumen issued tetap merujuk versi lama dan `snapshot_data` (BR-017).
- `generated_documents` berstatus `issued` immutable; koreksi = dokumen baru + pembatalan dokumen lama.
- Surat khitan hanya meminta pemeriksaan medis, penilaian, dan tindakan sesuai kebijakan rumah sakit (PRD §27); template tidak memuat keputusan medis.
- Surat pembaruan data agama hanya surat dukungan Islamic Center (PRD §28).
