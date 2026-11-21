# Repository Guidelines

## Project Structure & Module Organization

This is a Laravel 13 + Filament 5 application. The technical design lives in `docs/tdd/` (start at `docs/tdd/README.md`). Filament panels are configured in `app/Providers/Filament/` with resources under `app/Filament/Central/` and `app/Filament/Center/`; domain code goes in `app/Domain/<Module>/`. Shared PhpStorm run configurations are in `.run/`.

The target stack is Laravel + Filament + MySQL 8.4 + Redis, developed locally with Laravel Herd and PhpStorm. Follow `docs/tdd/` for module layout (`app/Domain/*`), database conventions, tenancy rules, and the implementation order; update those documents when the design changes.

When scaffolding the application, follow the selected framework’s directory conventions. Keep application code, automated tests, and static assets in clearly separated locations. Update this guide with the actual paths once they exist. Avoid treating IDE metadata as application configuration.

## Build, Test, and Development Commands

Setup and commands are documented in `README.md`. Tests: `vendor/bin/pest` (runs on MySQL database `muallaf_testing` via `.env.testing`). Format: `vendor/bin/pint`. Static analysis: `vendor/bin/phpstan analyse --memory-limit=1G`. Run migrations with `php artisan migrate --database=migrator`; the default `mysql` connection uses a restricted app user without DDL rights.

When introducing tooling, document exact setup and execution commands in `README.md`, including required runtime versions. Provide reproducible dependency installation and commit the appropriate dependency lockfile.

## Coding Style & Naming Conventions

Follow the selected language and framework’s standard style. Once source files exist, preserve their established indentation and naming conventions. Use descriptive names that reflect domain responsibilities.

Add a shared formatter or linter configuration when choosing the stack, and document its invocation. Keep formatting changes separate from unrelated functional changes.

## Testing Guidelines

No testing framework or coverage threshold is currently configured. Introduce tests alongside the first application features, using the framework’s standard test directory and discovery naming pattern. Cover expected behavior, validation failures, and relevant edge cases. Document the full test command and how to run an individual test.

## Commit & Pull Request Guidelines

No Git history is available to establish an existing commit convention. Use short, imperative commit subjects, such as `Add registration validation`, and keep each commit focused.

Pull requests should describe the change, its purpose, and validation performed. Link related issues when available and include screenshots for visible interface changes. Explicitly identify setup steps or configuration changes.

## Security & Configuration

Keep credentials, local environment files, and personal data out of version control. Provide placeholder values in example configuration files. Review generated files and IDE settings before committing them.
