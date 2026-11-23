# 03a. Matriks Role × Permission

> File ini dihasilkan otomatis dari `app/Domain/Identity/Rbac/RoleCatalog.php` dengan
> `php artisan rbac:matrix`. Jangan diedit manual: ubah katalognya, lalu jalankan ulang perintah.
> Test `RbacMatrixDocumentTest` gagal bila file ini tidak sama dengan kode.

Dasar: [03-tenancy-authorization.md](03-tenancy-authorization.md) §3.5–3.7.

## Ringkasan role

| Kode | Role | Nama Arab | Scope | Wajib MFA | Jumlah permission |
| --- | --- | --- | --- | --- | --- |
| SA | Super Admin | المشرف العام | Platform | Ya | 21 |
| CA | Central Admin | مسؤول المنصة | Platform | Ya | 21 |
| CAu | Central Auditor | المدقق المركزي | Platform | Ya | 5 |
| CR | Central Reporting | التقارير المركزية | Platform | Ya | 3 |
| SO | Support Officer | مسؤول الدعم الفني | Platform | Ya | 2 |
| CtA | Center Admin | مدير المركز | Kantor | Ya | 24 |
| HoD | Head of Dawah | رئيس قسم الدعوة | Kantor | Ya | 42 |
| Dai | Da'i | داعية | Kantor | – | 17 |
| Reg | Registration Officer | موظف التسجيل | Kantor | – | 8 |
| SV | Shahadah Verifier | مدقق الشهادة | Kantor | Ya | 7 |
| GC | Guidance Coordinator | منسق التأهيل | Kantor | – | 10 |
| Tch | Teacher | معلم | Kantor | – | 5 |
| EO | Examination Officer | مسؤول الاختبارات | Kantor | Ya | 7 |
| CO | Certificate Officer | مسؤول الشهادات | Kantor | Ya | 7 |
| AO | Administrative Officer | الموظف الإداري | Kantor | Ya | 8 |
| Apr | Approver | المعتمد | Kantor | Ya | 12 |
| TO | Transportation Officer | مسؤول النقل | Kantor | Ya | 9 |
| PO | Pilgrimage Officer | مسؤول الحج والعمرة | Kantor | – | 6 |
| LA | Local Auditor | المدقق المحلي | Kantor | – | 18 |

Role wajib MFA bila berscope platform, atau memegang permission dengan aksi: `assign_roles`, `approve`, `verify`, `reveal`, `review_transfer`, `finalize`, `issue`, `revoke`, `set_disclosure`, `view_sensitive`, `view_confidential`, `correct_origin`, `approve_void`.

## Matriks per modul

✓ = role memegang permission. Kolom pertama lima adalah role platform; sisanya role kantor.

### tenants

| Permission | SA | CA | CAu | CR | SO | CtA | HoD | Dai | Reg | SV | GC | Tch | EO | CO | AO | Apr | TO | PO | LA |
| --- | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: |
| `tenants.view` | ✓ | ✓ | ✓ | ✓ | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |  | ✓ |
| `tenants.create` | ✓ | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |
| `tenants.update` | ✓ | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |
| `tenants.activate` | ✓ | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |
| `tenants.suspend` | ✓ | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |

### users

| Permission | SA | CA | CAu | CR | SO | CtA | HoD | Dai | Reg | SV | GC | Tch | EO | CO | AO | Apr | TO | PO | LA |
| --- | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: |
| `users.view` | ✓ | ✓ | ✓ |  | ✓ | ✓ |  |  |  |  |  |  |  |  |  |  |  |  | ✓ |
| `users.create` | ✓ | ✓ |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |  |
| `users.update` | ✓ | ✓ |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |  |
| `users.deactivate` | ✓ | ✓ |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |  |
| `users.assign_roles` | ✓ | ✓ |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |  |

### master

| Permission | SA | CA | CAu | CR | SO | CtA | HoD | Dai | Reg | SV | GC | Tch | EO | CO | AO | Apr | TO | PO | LA |
| --- | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: |
| `master.manage` | ✓ | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |

### dais

| Permission | SA | CA | CAu | CR | SO | CtA | HoD | Dai | Reg | SV | GC | Tch | EO | CO | AO | Apr | TO | PO | LA |
| --- | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: |
| `dais.view` |  |  |  |  |  | ✓ | ✓ |  |  |  |  |  |  |  |  |  |  |  | ✓ |
| `dais.create` |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |  |
| `dais.update` |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |  |
| `dais.view_workload` |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |

### prospects

| Permission | SA | CA | CAu | CR | SO | CtA | HoD | Dai | Reg | SV | GC | Tch | EO | CO | AO | Apr | TO | PO | LA |
| --- | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: |
| `prospects.view` |  |  |  |  |  | ✓ | ✓ |  | ✓ | ✓ |  |  |  |  |  |  |  |  | ✓ |
| `prospects.view_own` |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |
| `prospects.create` |  |  |  |  |  |  | ✓ | ✓ |  |  |  |  |  |  |  |  |  |  |  |
| `prospects.update` |  |  |  |  |  |  | ✓ | ✓ |  |  |  |  |  |  |  |  |  |  |  |
| `prospects.assign_dai` |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |
| `prospects.change_status` |  |  |  |  |  |  | ✓ | ✓ |  |  |  |  |  |  |  |  |  |  |  |
| `prospects.transfer` |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |

### converts

| Permission | SA | CA | CAu | CR | SO | CtA | HoD | Dai | Reg | SV | GC | Tch | EO | CO | AO | Apr | TO | PO | LA |
| --- | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: |
| `converts.view` |  |  |  |  |  | ✓ | ✓ |  | ✓ | ✓ | ✓ |  | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `converts.view_own` |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |
| `converts.view_class` |  |  |  |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |
| `converts.create` |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |  |
| `converts.update` |  |  |  |  |  |  | ✓ |  | ✓ |  |  |  |  |  |  |  |  |  |  |
| `converts.view_sensitive` |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |
| `converts.assign_dai` |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |
| `converts.set_disclosure` |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |
| `converts.transfer` |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |
| `converts.review_transfer` |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |
| `converts.correct_origin` |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |
| `converts.view_restricted` |  |  |  |  |  | ✓ | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |
| `converts.view_high_confidentiality` |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |
| `converts.change_status` |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |

### identity

| Permission | SA | CA | CAu | CR | SO | CtA | HoD | Dai | Reg | SV | GC | Tch | EO | CO | AO | Apr | TO | PO | LA |
| --- | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: |
| `identity.national_search` |  |  |  |  |  |  | ✓ |  | ✓ |  |  |  |  |  |  |  |  |  |  |
| `identity.verify` |  |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |
| `identity.reveal` |  |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |

### shahadah

| Permission | SA | CA | CAu | CR | SO | CtA | HoD | Dai | Reg | SV | GC | Tch | EO | CO | AO | Apr | TO | PO | LA |
| --- | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: |
| `shahadah.record` |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |  |
| `shahadah.verify` |  |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |
| `shahadah.request_void` |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |
| `shahadah.approve_void` |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |

### duplicates

| Permission | SA | CA | CAu | CR | SO | CtA | HoD | Dai | Reg | SV | GC | Tch | EO | CO | AO | Apr | TO | PO | LA |
| --- | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: |
| `duplicates.review` |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |

### followups

| Permission | SA | CA | CAu | CR | SO | CtA | HoD | Dai | Reg | SV | GC | Tch | EO | CO | AO | Apr | TO | PO | LA |
| --- | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: |
| `followups.view` |  |  |  |  |  |  | ✓ | ✓ |  |  | ✓ |  |  |  |  |  |  |  | ✓ |
| `followups.create` |  |  |  |  |  |  | ✓ | ✓ |  |  |  |  |  |  |  |  |  |  |  |
| `followups.view_confidential` |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |

### dawah

| Permission | SA | CA | CAu | CR | SO | CtA | HoD | Dai | Reg | SV | GC | Tch | EO | CO | AO | Apr | TO | PO | LA |
| --- | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: |
| `dawah.activities.create` |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |
| `dawah.activities.verify` |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |
| `dawah.plans.view` |  |  |  |  |  | ✓ | ✓ | ✓ |  |  |  |  |  |  |  | ✓ | ✓ |  | ✓ |
| `dawah.plans.create` |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |
| `dawah.plans.submit` |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |
| `dawah.plans.review` |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |
| `dawah.plans.approve` |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |
| `dawah.reports.submit` |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |
| `dawah.reports.verify` |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |

### budget

| Permission | SA | CA | CAu | CR | SO | CtA | HoD | Dai | Reg | SV | GC | Tch | EO | CO | AO | Apr | TO | PO | LA |
| --- | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: |
| `budget.view` |  |  |  |  |  | ✓ | ✓ |  |  |  |  |  |  |  |  | ✓ |  |  | ✓ |
| `budget.approve` |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  | ✓ |  |  |  |
| `budget.record_actual` |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  | ✓ |  |  |

### materials

| Permission | SA | CA | CAu | CR | SO | CtA | HoD | Dai | Reg | SV | GC | Tch | EO | CO | AO | Apr | TO | PO | LA |
| --- | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: |
| `materials.view` | ✓ | ✓ |  |  |  | ✓ | ✓ | ✓ |  |  |  |  |  |  |  |  |  |  | ✓ |
| `materials.create` | ✓ | ✓ |  |  |  |  | ✓ | ✓ |  |  |  |  |  |  |  |  |  |  |  |
| `materials.approve` | ✓ | ✓ |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |
| `materials.distribute` |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |

### guidance

| Permission | SA | CA | CAu | CR | SO | CtA | HoD | Dai | Reg | SV | GC | Tch | EO | CO | AO | Apr | TO | PO | LA |
| --- | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: |
| `guidance.view` |  |  |  |  |  | ✓ | ✓ |  |  |  | ✓ | ✓ | ✓ | ✓ |  |  |  |  | ✓ |
| `guidance.programs.manage` |  |  |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |
| `guidance.classes.manage` |  |  |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |
| `guidance.enroll` |  |  |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |
| `guidance.attendance.record` |  |  |  |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |
| `guidance.progress.record` |  |  |  |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |

### exams

| Permission | SA | CA | CAu | CR | SO | CtA | HoD | Dai | Reg | SV | GC | Tch | EO | CO | AO | Apr | TO | PO | LA |
| --- | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: |
| `exams.view` |  |  |  |  |  | ✓ |  |  |  |  | ✓ |  | ✓ | ✓ |  |  |  |  | ✓ |
| `exams.manage` |  |  |  |  |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |
| `exams.grade` |  |  |  |  |  |  |  |  |  |  |  | ✓ | ✓ |  |  |  |  |  |  |
| `exams.finalize` |  |  |  |  |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |

### certificates

| Permission | SA | CA | CAu | CR | SO | CtA | HoD | Dai | Reg | SV | GC | Tch | EO | CO | AO | Apr | TO | PO | LA |
| --- | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: |
| `certificates.view` |  |  |  |  |  | ✓ | ✓ |  |  |  |  |  |  | ✓ |  | ✓ |  |  | ✓ |
| `certificates.request` |  |  |  |  |  |  |  |  |  |  | ✓ |  | ✓ |  |  |  |  |  |  |
| `certificates.approve` |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  | ✓ |  |  |  |
| `certificates.issue` |  |  |  |  |  |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |
| `certificates.revoke` |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  | ✓ |  |  |  |

### documents

| Permission | SA | CA | CAu | CR | SO | CtA | HoD | Dai | Reg | SV | GC | Tch | EO | CO | AO | Apr | TO | PO | LA |
| --- | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: |
| `documents.view` |  |  |  |  |  | ✓ |  |  | ✓ | ✓ |  |  |  | ✓ | ✓ |  |  |  | ✓ |
| `documents.upload` |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  | ✓ |  |  |  |  |
| `documents.verify` |  |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |
| `documents.generate` |  |  |  |  |  |  |  |  |  |  |  |  |  | ✓ | ✓ |  |  |  |  |
| `documents.templates.manage` | ✓ | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |

### submissions

| Permission | SA | CA | CAu | CR | SO | CtA | HoD | Dai | Reg | SV | GC | Tch | EO | CO | AO | Apr | TO | PO | LA |
| --- | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: |
| `submissions.view` |  |  |  |  |  | ✓ | ✓ |  |  |  |  |  |  |  | ✓ | ✓ |  |  | ✓ |
| `submissions.create` |  |  |  |  |  |  |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |
| `submissions.verify` |  |  |  |  |  |  |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |
| `submissions.approve` |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  | ✓ |  |  |  |
| `submissions.process` |  |  |  |  |  |  |  |  |  |  |  |  |  |  | ✓ |  |  |  |  |

### transport

| Permission | SA | CA | CAu | CR | SO | CtA | HoD | Dai | Reg | SV | GC | Tch | EO | CO | AO | Apr | TO | PO | LA |
| --- | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: |
| `transport.view` |  |  |  |  |  | ✓ | ✓ |  |  |  |  |  |  |  |  |  | ✓ |  | ✓ |
| `transport.request` |  |  |  |  |  |  |  | ✓ |  |  | ✓ |  |  |  |  |  | ✓ | ✓ |  |
| `transport.approve` |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |  | ✓ |  |  |
| `transport.dispatch` |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  | ✓ |  |  |
| `transport.fleet.manage` |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  | ✓ |  |  |

### pilgrimage

| Permission | SA | CA | CAu | CR | SO | CtA | HoD | Dai | Reg | SV | GC | Tch | EO | CO | AO | Apr | TO | PO | LA |
| --- | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: |
| `pilgrimage.view` |  |  |  |  |  | ✓ | ✓ |  |  |  |  |  |  |  |  | ✓ | ✓ | ✓ | ✓ |
| `pilgrimage.programs.manage` |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  | ✓ |  |
| `pilgrimage.register` |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  | ✓ |  |
| `pilgrimage.check_eligibility` |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  | ✓ |  |
| `pilgrimage.approve` |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  | ✓ |  |  |  |

### international

| Permission | SA | CA | CAu | CR | SO | CtA | HoD | Dai | Reg | SV | GC | Tch | EO | CO | AO | Apr | TO | PO | LA |
| --- | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: |
| `international.referrals.create` |  |  |  |  |  |  | ✓ | ✓ |  |  |  |  |  |  |  |  |  |  |  |
| `international.referrals.send` |  |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |
| `international.organizations.manage` | ✓ | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |

### reports

| Permission | SA | CA | CAu | CR | SO | CtA | HoD | Dai | Reg | SV | GC | Tch | EO | CO | AO | Apr | TO | PO | LA |
| --- | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: |
| `reports.view_office` |  |  |  |  |  | ✓ | ✓ |  |  |  | ✓ |  |  |  |  | ✓ |  |  | ✓ |
| `reports.view_national` | ✓ | ✓ | ✓ | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |
| `reports.export` | ✓ |  |  | ✓ |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |  |

### audit

| Permission | SA | CA | CAu | CR | SO | CtA | HoD | Dai | Reg | SV | GC | Tch | EO | CO | AO | Apr | TO | PO | LA |
| --- | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: |
| `audit.view_office` |  |  |  |  |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |  | ✓ |
| `audit.view_national` | ✓ | ✓ | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |

### privileged

| Permission | SA | CA | CAu | CR | SO | CtA | HoD | Dai | Reg | SV | GC | Tch | EO | CO | AO | Apr | TO | PO | LA |
| --- | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: |
| `privileged.request` | ✓ | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |
| `privileged.approve` | ✓ |  | ✓ |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |

## Permission tanpa role

Tidak ada: setiap permission dipegang minimal satu role.
