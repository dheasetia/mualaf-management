# 03. Tenancy dan Authorization

Setiap Islamic Center adalah satu tenant dalam shared database, shared schema. Isolasi ditegakkan berlapis: tenant context dari membership terverifikasi, global scope di model, policy per aksi, dan test isolasi wajib.

## 3.1 Tenant context

`App\Support\Tenancy\TenantContext` adalah singleton per request/job yang menyimpan:

| Atribut | Sumber |
| --- | --- |
| `tenantId` | Membership aktif yang diverifikasi middleware |
| `tenantUuid` | Dari record tenant |
| `actorId` | User terautentikasi |
| `scope` | `center` atau `central` |
| `privilegedGrantId` | Grant privileged access aktif (bila ada) |

Aturan:

1. Middleware `SetTenantContext` (panel Center) memverifikasi `user_tenant_memberships` aktif untuk tenant pada URL. Gagal → 403, tidak ada fallback.
2. Nilai `tenant_id` pada insert diisi oleh model observer dari `TenantContext`; input form dengan `tenant_id` diabaikan (kolom di `$guarded`).
3. Context disalin ke `Illuminate\Support\Facades\Context` sehingga otomatis ikut ke queued job; job memanggil `TenantContext::restoreFromContext()` di middleware job dan gagal bila context kosong pada job tenant-scoped.
4. Console command tenant-scoped wajib menerima `--tenant=<code>` eksplisit.

## 3.2 Kategori tabel

| Kategori | Contoh | Scope |
| --- | --- | --- |
| Global master | `regions`, `cities`, `countries`, `languages`, `religions`, `budget_categories`, `dai_activity_types` | Baca semua; tulis hanya Central |
| Global atau tenant | `companies`, `dawah_materials`, `document_templates`, `certificate_templates` | `tenant_id NULL` = nasional (dikelola Central), terisi = milik kantor |
| Tenant-owned | `prospects`, `dais`, `dawah_activity_plans`, `guidance_classes`, `vehicles` | Global scope `BelongsToTenant` pada `tenant_id` |
| Convert-owned | `converts` | Global scope `ConvertScope` pada `current_tenant_id` |
| Convert child | `convert_identifiers`, `convert_contacts`, `convert_employments`, `convert_residences`, `convert_contact_persons`, `convert_home_*` | Diakses hanya melalui relasi convert yang sudah di-scope (`whereHas` ke `converts.current_tenant_id`) |
| Subject history | `shahadah_records`, `dai_follow_ups`, `convert_long_term_follow_ups`, `convert_status_histories`, `enrollments`, `certificates` | Aturan 3.3 |
| Platform | `audit_logs`, `privileged_access_requests`, `export_jobs` | Central, atau tenant sendiri untuk baris `tenant_id` miliknya |

Model convert child menggunakan trait `ScopedThroughConvert` yang menambahkan global scope `whereHas('convert')`. Query langsung tanpa scope hanya diizinkan di dalam domain service yang sudah memverifikasi akses (dengan `withoutGlobalScope` yang dibungkus helper `TenantContext::bypass(reason)` dan tercatat di audit).

## 3.3 Subject-scoped history

Histori mengikuti subjek (convert), bukan kantor. Setiap baris histori tetap dicap `tenant_id` kantor yang menulisnya.

| Pihak | Hak baca histori convert |
| --- | --- |
| Kantor aktif (`current_tenant_id`) | Seluruh histori convert dari semua kantor sebelumnya, sesuai permission role dan disclosure level |
| Kantor asal setelah transfer | Agregat saja (jumlah, tanggal syahadat, status sertifikat yang diterbitkannya); bukan profil terkini, kontak, atau follow-up setelah transfer |
| Kantor lama lain (perantara) | Tidak ada akses setelah transfer selesai |
| Central | Agregat; detail hanya via privileged access |

Implementasi: policy `ConvertHistoryPolicy::view(User, Convert)` memeriksa `convert.current_tenant_id === context.tenantId`, lalu query histori dilakukan tanpa filter `tenant_id`. Untuk statistik kantor asal dipakai tabel/aggregasi berbasis `origin_tenant_id` (lihat Reporting).

## 3.4 Pencarian nasional

Pencarian identitas lintas tenant (duplicate check dan pengajuan transfer) adalah satu-satunya jalan baca lintas tenant pada operasional normal. Hasilnya dibatasi:

| Field yang dikembalikan | Ditampilkan |
| --- | --- |
| Ditemukan / tidak | Ya |
| Kode dan nama kantor aktif | Ya |
| Nama convert | Inisial saja (misal "A. R.") kecuali disclosure `normal` dan pencari punya `converts.transfer` |
| Tanggal syahadat, kontak, alamat, agama sebelumnya | Tidak |

Setiap pencarian nasional dicatat di `audit_logs` (action `identity.national_search`) dan di-rate-limit 30/jam per user.

## 3.5 RBAC

### Keputusan

Memakai tabel RBAC sendiri (`roles`, `permissions`, `role_permissions`, `user_role_assignments`) bukan `spatie/laravel-permission`, karena:

- assignment membutuhkan `tenant_id` nullable (role platform) dan histori `assigned_by`/`revoked_at`;
- satu user bisa memegang role berbeda di tenant berbeda;
- resolusi permission harus sadar tenant context dan disclosure level.

`Gate::before` tidak dipakai untuk bypass; Super Admin tetap melalui policy (dengan aturan privileged access untuk data sensitif).

### Resolusi permission

```
can(user, 'converts.view') =
  exists user_role_assignments ura
    join role_permissions rp on rp.role_id = ura.role_id
    join permissions p on p.id = rp.permission_id and p.code = 'converts.view'
  where ura.user_id = user.id
    and ura.revoked_at is null
    and (ura.tenant_id = context.tenantId or (roles.scope_type = 'platform' and context.scope = 'central'))
```

Hasil di-cache per request (bukan global): kunci `tenant:{tenant_id}:user:{user_id}:perms`, TTL 5 menit, di-invalidate saat assignment berubah.

### Lapisan pemeriksaan

1. **Permission**: apakah role mengizinkan aksi (`converts.update`).
2. **Tenant**: apakah record berada pada tenant context.
3. **Ownership/relationship**: untuk Da'i — hanya subjek yang dia pegang (Primary atau assignment aktif); untuk Teacher — hanya kelas yang ditugaskan.
4. **Disclosure**: field atau record dengan level `restricted`/`high_confidentiality` (lihat [04-identity-privacy.md](04-identity-privacy.md)).
5. **State**: aksi hanya valid pada status tertentu (misal edit plan hanya saat `draft`/`needs_revision`).

## 3.6 Katalog permission

Format `module.action` atau `module.resource.action`. Sumber kebenaran adalah `app/Domain/Identity/Rbac/PermissionCatalog.php`; tabel di bawah adalah ringkasannya. `RbacSeeder` menyinkronkan katalog ke database pada setiap deploy.

| Modul | Permission |
| --- | --- |
| tenants | `tenants.view`, `tenants.create`, `tenants.update`, `tenants.activate`, `tenants.suspend` |
| users | `users.view`, `users.create`, `users.update`, `users.deactivate`, `users.assign_roles` |
| master | `master.manage` |
| dais | `dais.view`, `dais.create`, `dais.update`, `dais.view_workload` |
| prospects | `prospects.view`, `prospects.view_own`, `prospects.create`, `prospects.update`, `prospects.assign_dai`, `prospects.change_status`, `prospects.transfer` |
| converts | `converts.view`, `converts.view_own`, `converts.view_class`, `converts.create`, `converts.update`, `converts.view_sensitive`, `converts.assign_dai`, `converts.set_disclosure`, `converts.transfer`, `converts.review_transfer`, `converts.correct_origin` |
| identity | `identity.national_search`, `identity.verify`, `identity.reveal` |
| shahadah | `shahadah.record`, `shahadah.verify` |
| duplicates | `duplicates.review` |
| followups | `followups.view`, `followups.create`, `followups.view_confidential` |
| dawah | `dawah.activities.create`, `dawah.activities.verify`, `dawah.plans.view`, `dawah.plans.create`, `dawah.plans.submit`, `dawah.plans.review`, `dawah.plans.approve`, `dawah.reports.submit`, `dawah.reports.verify` |
| budget | `budget.view`, `budget.approve`, `budget.record_actual` |
| materials | `materials.view`, `materials.create`, `materials.approve`, `materials.distribute` |
| guidance | `guidance.view`, `guidance.programs.manage`, `guidance.classes.manage`, `guidance.enroll`, `guidance.attendance.record`, `guidance.progress.record` |
| exams | `exams.view`, `exams.manage`, `exams.grade`, `exams.finalize` |
| certificates | `certificates.view`, `certificates.request`, `certificates.approve`, `certificates.issue`, `certificates.revoke` |
| documents | `documents.view`, `documents.upload`, `documents.verify`, `documents.generate`, `documents.templates.manage` |
| submissions | `submissions.view`, `submissions.create`, `submissions.verify`, `submissions.approve`, `submissions.process` |
| transport | `transport.view`, `transport.request`, `transport.approve`, `transport.dispatch`, `transport.fleet.manage` |
| pilgrimage | `pilgrimage.view`, `pilgrimage.programs.manage`, `pilgrimage.register`, `pilgrimage.check_eligibility`, `pilgrimage.approve` |
| international | `international.referrals.create`, `international.referrals.send`, `international.organizations.manage` |
| reports | `reports.view_office`, `reports.view_national`, `reports.export` |
| audit | `audit.view_office`, `audit.view_national` |
| privileged | `privileged.request`, `privileged.approve` |

## 3.7 Matriks role × permission (ringkas)

Legenda: **F** = penuh di modul, **O** = hanya subjek sendiri (own), **R** = baca, **A** = approve/verify, **–** = tidak ada.

| Role | Prospects | Converts | Identity / Shahadah | Follow-up | Dawah plans | Guidance | Exams | Certificates | Submissions | Transport | Pilgrimage | Reports | Audit |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Super Admin (platform) | – | – | – | – | – | – | – | – | – | – | – | R nasional | R nasional |
| Central Admin | – | – | – | – | – | – | – | – | – | – | – | R nasional | R nasional |
| Central Auditor | – | – | – | – | – | – | – | – | – | – | – | R nasional | R nasional |
| Central Reporting | – | – | – | – | – | – | – | – | – | – | – | R + export nasional | – |
| Support Officer | – | – | – | – | – | – | – | – | – | – | – | – | – |
| Center Admin | R | R | – | – | R | R | R | R | R | R | R | R + export | R kantor |
| Head of Dawah | F + assign | F + assign + transfer review | R | F (termasuk confidential) | A | R | – | R | R | A | R | R | – |
| Da'i | O | O | – | O | O (create/submit/report) | – | – | – | – | request | – | – | – |
| Registration Officer | R | create + update | national search, record | – | – | – | – | – | – | – | – | – | – |
| Shahadah Verifier | R | R | verify, `identity.reveal` | – | – | – | – | – | – | – | – | – | – |
| Guidance Coordinator | – | R | – | R | – | F | R | request | – | request | – | R | – |
| Teacher | – | R kelas sendiri (`converts.view_class`) | – | – | – | attendance + progress (kelas sendiri) | grade (kelas sendiri) | – | – | – | – | – | – |
| Examination Officer | – | R | – | – | – | R | F + finalize | request | – | – | – | – | – |
| Certificate Officer | – | R | – | – | – | R | R | issue | – | – | – | – | – |
| Administrative Officer | – | R | – | – | – | – | – | – | create + process | – | – | – | – |
| Approver | – | R | – | – | A (budget) | – | – | approve | approve | – | approve | R | – |
| Transportation Officer | – | R (pickup saja) | – | – | R | – | – | – | – | F | R | – | – |
| Pilgrimage Officer | – | R | – | – | – | – | – | – | – | request | F | – | – |
| Local Auditor | R | R | R | R | R | R | R | R | R | R | R | R | R kantor |

Catatan:

- Role platform tidak punya akses data individual kantor secara default; akses hanya via privileged access grant (§4.8 di [04-identity-privacy.md](04-identity-privacy.md)).
- Support Officer hanya mengelola tiket teknis dan impersonation-less troubleshooting; tidak ada impersonation user.
- Segregation of duties: user yang mencatat syahadat tidak boleh memverifikasinya; pengusul plan tidak boleh menyetujui plan sendiri; pengaju transfer tidak boleh me-review di kantor lama (berbeda tenant secara alami).
- Matriks lengkap per permission ada di `app/Domain/Identity/Rbac/RoleCatalog.php` (disinkronkan oleh `RbacSeeder`) dan harus disetujui Islamic Center Dammam. Perubahan role sistem hanya lewat file ini.
- Role platform hanya berlaku tanpa tenant context; role kantor hanya berlaku di tenant tempat role dipegang, selama membership dan tenant aktif.
- Role wajib MFA bila berscope platform, atau memegang permission dengan aksi `assign_roles`, `approve`, `verify`, `reveal`, `review_transfer`, `finalize`, `issue`, `revoke`, `set_disclosure`, `view_sensitive`, `view_confidential`, atau `correct_origin`. Panel Central mewajibkan MFA untuk semua pengguna; panel Center mengarahkan pemegang role wajib-MFA ke halaman profil sampai authenticator aktif (`RequireMfaForPrivilegedRoles`).
- Keputusan review matriks (22-11-2026): role wajib-MFA tetap mencakup Administrative Officer dan Transportation Officer; Teacher memakai `converts.view_class` (hanya peserta kelasnya); `budget.approve` dan `certificates.revoke` tetap hanya pada Approver (terpisah dari pengusul/penerbit); Center Admin boleh memberikan semua role kantor di kantornya, termasuk role sensitif (setiap pemberian tercatat di audit log mulai langkah 5); Central Auditor ikut memegang `privileged.approve`.
- Role pertama (Super Admin) diberikan lewat `php artisan rbac:grant-super-admin {email}`; selanjutnya hanya pemegang `users.assign_roles`, dan tidak ada yang dapat mengubah role-nya sendiri.
