# 02. Development Environment (Herd + PhpStorm)

Pengembangan lokal memakai Laravel Herd di macOS dan PhpStorm dengan plugin Laravel Idea. Proyek berada di `~/Herd/muallaf-management` dan dilayani Herd di `https://muallaf-management.test`.

## 2.1 Kebutuhan runtime

| Komponen | Versi | Sumber |
| --- | --- | --- |
| PHP | 8.4 | Herd |
| Composer | 2.x | Herd |
| Node.js | 24 LTS | Herd / nvm (untuk Vite dan Filament theme) |
| MySQL | 8.4 | Homebrew `mysql@8.4` atau Herd Pro services |
| Redis | 7.x | Herd Pro services atau `brew install redis` |
| Chromium | terbaru | Puppeteer (untuk Browsershot) |
| Mailpit | terbaru | Herd Pro services atau `brew install mailpit` |

Ekstensi PHP wajib: `pdo_mysql`, `redis` (atau gunakan `predis/predis`), `intl`, `mbstring`, `gd`/`imagick`, `zip`, `bcmath`, `sodium`.

## 2.2 Paket utama

| Paket | Fungsi |
| --- | --- |
| `laravel/framework` (versi stabil terbaru, ≥ 12) | Framework |
| `filament/filament` (≥ 4) | Panel Central dan Center, MFA, tenancy |
| `laravel/horizon` | Queue monitoring |
| `spatie/laravel-data` | DTO untuk Actions |
| `spatie/browsershot` | HTML → PDF via Chromium |
| `simplesoftwareio/simple-qrcode` atau `chillerlan/php-qrcode` | QR verifikasi sertifikat |
| `league/flysystem-aws-s3-v3` | Object storage |
| `pestphp/pest` + `pestphp/pest-plugin-laravel` | Test |
| `larastan/larastan` | Static analysis |
| `laravel/pint` | Formatter |
| `barryvdh/laravel-ide-helper` (dev) | Helper untuk PhpStorm (opsional bila Laravel Idea dipakai) |

RBAC memakai implementasi sendiri sesuai skema di [06-data-model-core.md](06-data-model-core.md) (lihat keputusan di [03-tenancy-authorization.md](03-tenancy-authorization.md)).

## 2.3 Setup awal

Folder proyek sudah berisi `.idea/`, `AGENTS.md`, dan `docs/`, sehingga `composer create-project` tidak bisa langsung dijalankan di folder ini (folder harus kosong). Scaffold di folder sementara lalu pindahkan:

```bash
cd ~/Herd
composer create-project laravel/laravel muallaf-scaffold
rsync -a --ignore-existing muallaf-scaffold/ muallaf-management/
rm -rf muallaf-scaffold
cd muallaf-management
git init
herd secure muallaf-management
php artisan key:generate
```

`.env` hasil scaffold tetap lokal; pastikan `.gitignore` memuat `.env` dan `.idea/workspace.xml` sebelum commit pertama.

Database dan user lokal (dua user: owner untuk migrasi, app dengan hak terbatas):

```bash
mysql -uroot -e "
CREATE DATABASE muallaf CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE DATABASE muallaf_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE USER 'muallaf_owner'@'localhost' IDENTIFIED BY 'change-me';
CREATE USER 'muallaf_app'@'localhost' IDENTIFIED BY 'change-me';
GRANT ALL PRIVILEGES ON muallaf.* TO 'muallaf_owner'@'localhost';
GRANT ALL PRIVILEGES ON muallaf_testing.* TO 'muallaf_owner'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON muallaf.* TO 'muallaf_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON muallaf_testing.* TO 'muallaf_app'@'localhost';"
```

Pembatasan `UPDATE, DELETE` pada tabel append-only dilakukan oleh migration khusus (lihat [05-database-conventions.md](05-database-conventions.md) §5.4). Migrasi dijalankan dengan koneksi `migrator` (`php artisan migrate --database=migrator`); test memakai user owner agar `RefreshDatabase` dapat membuat ulang skema, dan test append-only memakai koneksi app untuk membuktikan penolakan.

`.env` lokal (nilai placeholder; jangan commit `.env`):

```dotenv
APP_URL=https://muallaf-management.test
APP_LOCALE=ar
APP_FALLBACK_LOCALE=en
APP_TIMEZONE=UTC
DISPLAY_TIMEZONE=Asia/Riyadh

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=muallaf
DB_USERNAME=muallaf_app
DB_PASSWORD=change-me

# Koneksi kedua untuk migrasi/owner (grant penuh); app memakai user terbatas
DB_MIGRATOR_USERNAME=muallaf_owner
DB_MIGRATOR_PASSWORD=change-me

SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis

FILESYSTEM_DISK=private
IDENTITY_HMAC_KEY=base64:generate-with-artisan
IDENTITY_HMAC_KEY_VERSION=1

MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
```

`IDENTITY_HMAC_KEY` dibuat dengan perintah artisan khusus (`php artisan identity:generate-key`) dan tidak pernah sama dengan `APP_KEY`.

## 2.4 Konfigurasi PhpStorm

| Pengaturan | Nilai |
| --- | --- |
| PHP interpreter | Herd PHP 8.4 (`~/Library/Application Support/Herd/bin/php`) |
| Composer | Herd composer, `composer.json` proyek |
| Plugin | Laravel Idea, Pest, .env files, Tailwind CSS |
| Code style | Settings → PHP → Code Style → set from Laravel Pint / PSR-12; aktifkan "Reformat on save" hanya untuk file yang diubah |
| Quality tools | Laravel Pint (External Formatter), PHPStan dengan `phpstan.neon` proyek |
| Database | Data source MySQL `muallaf` dan `muallaf_testing` untuk introspeksi skema |
| Xdebug | Herd → PHP → Xdebug aktif; PhpStorm listen port 9003 |
| File watchers | Tidak diperlukan; Pint dijalankan via pre-commit atau run configuration |

### Run configurations (simpan di `.run/` agar ikut repository)

| Nama | Jenis | Perintah |
| --- | --- | --- |
| Tests (all) | Pest | `vendor/bin/pest` |
| Tests (tenant isolation) | Pest | `vendor/bin/pest --group=tenant-isolation` |
| Tests (concurrency) | Pest | `vendor/bin/pest --group=concurrency` |
| Pint | Shell | `vendor/bin/pint` |
| Larastan | Shell | `vendor/bin/phpstan analyse --memory-limit=1G` |
| Horizon | Shell | `php artisan horizon` |
| Scheduler | Shell | `php artisan schedule:work` |
| Vite | npm | `npm run dev` |
| Fresh seed | Shell | `php artisan migrate:fresh --seed --seeder=DevelopmentSeeder` |

Menjalankan satu test:

```bash
vendor/bin/pest tests/Feature/Transfers/ApproveTransferTest.php --filter="cannot complete twice"
```

## 2.5 Seeder pengembangan

`DevelopmentSeeder` membuat data sintetis, tidak pernah data produksi:

- 3 tenant: `DMM` (Dammam), `RUH` (Riyadh), `JED` (Jeddah).
- Satu user per role per tenant, password dari `DEV_SEED_PASSWORD` di `.env` (bukan hardcode).
- 20 prospect, 30 convert dengan identifier sintetis (nomor Iqamah fiktif berawalan `9`), 2 transfer dalam berbagai status.
- Master data lengkap (region, kota, negara, bahasa, agama, kategori budget, jenis aktivitas da'i).

## 2.6 Alur kerja harian

1. Buat branch per fitur: `feature/<modul>-<ringkas>`.
2. Tulis migration + model + factory, lalu test (Pest) sebelum Filament resource.
3. Jalankan `pint`, `phpstan`, dan `pest` sebelum commit.
4. Gunakan Laravel Idea untuk generate model/migration/factory; review hasil generate terhadap konvensi di [05-database-conventions.md](05-database-conventions.md).
