# 14. Testing

Test memakai Pest dengan database MySQL nyata (`muallaf_testing`), bukan SQLite, karena generated column, unique index, locking, dan trigger adalah bagian dari aturan bisnis.

## 14.1 Struktur

```
tests/
├── Arch/                  # aturan dependensi dan konvensi
├── Unit/                  # enum transitions, fingerprint, kalkulasi
├── Feature/
│   ├── Tenancy/
│   ├── Prospects/
│   ├── Registration/
│   ├── Transfers/
│   ├── Guidance/
│   ├── Certificates/
│   └── ...                # satu folder per modul domain
├── TenantIsolation/       # @group tenant-isolation
├── Concurrency/           # @group concurrency
└── Support/               # helper: actingAsRole(), inTenant(), factories state
```

Perintah:

```bash
vendor/bin/pest                                   # semua test
vendor/bin/pest --parallel                        # paralel (database per proses)
vendor/bin/pest --group=tenant-isolation
vendor/bin/pest --group=concurrency
vendor/bin/pest tests/Feature/Transfers --filter="cannot complete twice"
vendor/bin/pest --coverage --min=80               # di CI
```

Test memakai `RefreshDatabase` dengan migration (bukan schema dump) agar generated column dan trigger ikut. Concurrency test memakai `DatabaseMigrations` karena butuh koneksi terpisah yang melihat commit.

## 14.2 Jenis test

| Jenis | Cakupan |
| --- | --- |
| Arch | Filament tidak memanggil Model `::create/update` langsung untuk entitas ber-state; modul domain tidak mengimpor model modul lain; `strict_types` di semua file; tidak ada `dd/dump` |
| Unit | Transisi setiap enum status; `isActive()` sinkron dengan generated column; normalisasi dan fingerprint identitas (digit Arab-Indic, spasi, checksum); kalkulasi kehadiran, nilai ujian, variance budget; format nomor |
| Feature | CRUD per resource; workflow lengkap; validasi gagal; authorization per role (allowed + denied) |
| Tenant isolation | read, update, delete, export, download, queue, cache untuk setiap model tenant-scoped |
| Concurrency | registrasi ganda, transfer simultan, alokasi nomor sertifikat, kapasitas kelas, kuota umrah |
| PDF | Render template Arab tanpa error, berisi nomor dan QR, checksum tersimpan |
| Browser (opsional) | Pest browser testing untuk alur utama RTL |

## 14.3 Critical tests (wajib hijau sebelum rilis)

| # | Test | File |
| --- | --- | --- |
| 1 | Iqamah yang sudah terdaftar tidak dapat membuat convert kedua (termasuk di tenant lain dan saat identifier lama masih `unverified`) | `Feature/Registration/DuplicateIdentityTest.php` |
| 2 | Tenant A tidak dapat membaca, mengubah, menghapus, mengekspor, atau mengunduh data tenant B | `TenantIsolation/*` |
| 3 | Transfer tidak dapat selesai dua kali | `Concurrency/TransferCompletionTest.php` |
| 4 | `origin_tenant_id` tidak dapat berubah melalui CRUD biasa (form Filament, mass assignment, `update()` langsung pada model) | `Feature/Converts/OriginImmutabilityTest.php` |
| 5 | Prospect tidak dapat menjadi convert tanpa workflow syahadat | `Feature/Prospects/ConversionGuardTest.php` |
| 6 | Teacher tidak dapat mengubah kelas lain | `Feature/Guidance/TeacherScopeTest.php` |
| 7 | Da'i tidak dapat melihat subjek yang bukan binaannya | `Feature/Dai/SubjectVisibilityTest.php` |
| 8 | Sertifikat yang dicabut gagal verifikasi | `Feature/Certificates/VerificationTest.php` |
| 9 | Lost contact tidak mengubah status convert maupun status keagamaan | `Feature/FollowUp/LostContactTest.php` |
| 10 | Dokumen issued tetap sama walau data sumber dan template berubah | `Feature/Documents/SnapshotImmutabilityTest.php` |
| 11 | Dua pengajuan transfer bersamaan untuk convert yang sama → satu ditolak | `Concurrency/TransferRequestTest.php` |
| 12 | Kantor baru dapat membaca histori follow-up dari kantor lama setelah transfer; kantor lama tidak dapat membaca follow-up baru | `Feature/Transfers/HistoryAccessTest.php` |
| 13 | Tabel append-only menolak UPDATE/DELETE di level database | `Feature/Audit/AppendOnlyTest.php` |
| 14 | Convert `high_confidentiality` tidak muncul di export/lembar pickup | `Feature/Privacy/DisclosureTest.php` |
| 15 | Queue job tanpa tenant context gagal, bukan berjalan tanpa scope | `TenantIsolation/QueueContextTest.php` |

## 14.4 Pola test tenant isolation

```php
dataset('tenant_scoped_resources', [
    'prospect' => [fn () => Prospect::factory()],
    'dai' => [fn () => Dai::factory()],
    'activity plan' => [fn () => DawahActivityPlan::factory()],
    // ... setiap model tenant-scoped wajib terdaftar; arch test memeriksa kelengkapan
]);

it('hides records of another tenant', function (Closure $factory) {
    [$tenantA, $tenantB] = Tenant::factory()->count(2)->create();
    $record = inTenant($tenantB, fn () => $factory()->create());

    actingAsRole('center_admin', $tenantA);

    expect($record::query()->find($record->id))->toBeNull();
    $this->get(resourceUrl($record, $tenantA))->assertNotFound();
})->with('tenant_scoped_resources')->group('tenant-isolation');
```

## 14.5 Pola test concurrency

```php
it('allows only one convert per identity under concurrent registration', function () {
    $results = runConcurrently(2, fn (Tenant $tenant) =>
        RecordShahadah::run(ShahadahData::fake(iqamah: '2123456789'), tenant: $tenant)
    , tenants: ['DMM', 'RUH']);

    expect($results->successful())->toHaveCount(1)
        ->and($results->failed()->first())->toBeInstanceOf(ConvertAlreadyRegistered::class)
        ->and(ConvertIdentifier::count())->toBe(1);
})->group('concurrency');
```

`runConcurrently` menjalankan proses PHP terpisah (`Symfony\Process` / `pcntl_fork`) dengan koneksi database sendiri.

## 14.6 Data test

- Factory untuk setiap model dengan state per status (`->approved()`, `->completed()`).
- Nomor identitas sintetis yang lolos checksum dari helper `FakeIdentity::iqamah()`.
- Tidak ada data produksi di test atau seeder.

## 14.7 UAT

- Skenario UAT per modul ditulis bersama Kepala Bagian Dakwah dan admin kantor pilot.
- UAT dilakukan di staging dengan data sintetis dan antarmuka Arab RTL.
- Kriteria lulus: semua skenario sukses, tidak ada bug critical/high terbuka.
