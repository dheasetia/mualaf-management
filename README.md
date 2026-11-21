# Muallaf Center — National Platform

Platform nasional untuk mengelola Islamic Center, da'i, prospect, dan mualaf. Desain teknis lengkap ada di [docs/tdd/README.md](docs/tdd/README.md).

## Stack

| Komponen | Versi |
| --- | --- |
| PHP | 8.4 (Laravel Herd) |
| Laravel | 13 |
| Filament | 5 (panel `/central` dan `/center`) |
| MySQL | 8.4 (produksi/CI); lokal saat ini Homebrew MySQL 9.2 |
| Redis | Rencana untuk session, cache, queue; lokal sementara memakai driver `database` |
| Test | Pest 5 di MySQL (`muallaf_testing`) |

## Setup lokal

Lihat [docs/tdd/02-development-environment.md](docs/tdd/02-development-environment.md) untuk detail. Ringkasnya:

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Isi kredensial database di `.env`, lalu buat `.env.testing` (salinan `.env` dengan `APP_ENV=testing`, `DB_DATABASE=muallaf_testing`, dan kredensial user owner). Kedua file tidak di-commit.

```bash
php artisan migrate --database=migrator
herd secure muallaf-management
```

Panel: `https://muallaf-management.test/central` dan `https://muallaf-management.test/center`.

## Perintah

| Tujuan | Perintah |
| --- | --- |
| Semua test | `vendor/bin/pest` |
| Test isolasi tenant | `vendor/bin/pest --group=tenant-isolation` |
| Satu test | `vendor/bin/pest tests/Feature/ExampleTest.php --filter="nama test"` |
| Format | `vendor/bin/pint` |
| Static analysis | `vendor/bin/phpstan analyse --memory-limit=1G` |
| Migrasi ulang + seed | `php artisan migrate:fresh --database=migrator --seed` |

Semua perintah di atas tersedia sebagai run configuration PhpStorm di folder `.run/`.

## CI

GitHub Actions (`.github/workflows/ci.yml`) berjalan pada setiap pull request dan push ke `main`: Pint, Larastan, `composer audit`, lalu Pest (termasuk grup `tenant-isolation`) di MySQL 8.4.
