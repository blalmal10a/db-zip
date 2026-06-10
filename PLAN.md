# db-zip Implementation Plan

## Done (v1)

- [x] MCP: `laravel-boost` configured in `opencode.json`
- [x] Skills: `skills/db-zip-backup.md`, `skills/db-zip-restore.md`, `skills/db-zip-config.md`, `skills/db-zip-role-access.md`
- [x] Plugins installed: `oh-my-opencode`, `opencode-notify`, `opencode-worktree`, `opencode-conductor`
- [x] `opencode.json` updated with plugin list
- [x] `config/db-zip.php` — backup paths, roles, middleware config
- [x] `src/DbZip.php` — core backup/restore class
- [x] `src/Http/Controllers/BackupController.php` — backup API endpoints
- [x] `src/Http/Controllers/RestoreController.php` — restore endpoint
- [x] `src/Http/Middleware/CheckBackupRole.php` — Spatie role middleware
- [x] `routes/db-zip.php` — config-driven routes
- [x] `resources/views/` — backup, restore, layout views
- [x] `database/migrations/create_db_zip_table.php.stub` — backup metadata table
- [x] Pest tests — 22 passing
- [x] Verification — PHPStan 0 errors, Pint clean

## Done (v2 — Chunking + Restore UI)

- [x] CSV chunking at 400 rows per file with zero-padded suffix (`_001`, `_002`)
- [x] `exportTableToCsv()` returns array of file paths
- [x] `restoreTable()` has `$append` param for chunked insert without truncate
- [x] Restore view groups chunks under parent table (collapsible)
- [x] Each chunk has individual status badge (Uploading / Success / Error)
- [x] Nginx error detection (non-JSON response → banner)
- [x] `BackupController::backupTable()` returns chunk count
- [x] `RestoreController::restoreTable()` accepts `append` flag
- [x] Fixed `deleteBackup()` (was always returning false)
- [x] Fixed `zipBackup()` CSV cleanup (was commented out)
- [x] Fixed path helpers for consistency between test and runtime

## Done (v3 — Security hardening + Config-driven routes)

- [x] SQL injection fix — backtick-escaped table names in all queries
- [x] Path traversal fix — `basename()` in download/delete
- [x] Connection isolation fix — `getDatabaseName()` uses correct connection param
- [x] CSV robustness — `preg_split()` for Windows/Mac line endings; BOM stripped correctly
- [x] `json_encode` uses `JSON_THROW_ON_ERROR`; `fopen` return checked
- [x] Exception messages logged, not leaked to users in responses
- [x] Removed debug `logger('here')` calls from controllers
- [x] Removed `hasMigration()` from provider; deleted migration stub file and `src/Models/`
- [x] Config: `required_roles` changed to `['super_admin']` only; `route` and `controllers` blocks added; `models` key removed
- [x] Routes fully config-driven — all URIs from `config('db-zip.route.*')`
- [x] Views receive `$route` object instead of hardcoded fetch URLs
- [x] `method_exists($user, 'hasAnyRole')` guard in `CheckBackupRole` middleware

## Done (v4 — Alpine.js rewrite, Tailwind via npm)

- [x] `package.json` with `alpinejs ^3`, `tailwindcss ^4`, `@tailwindcss/cli ^4`
- [x] `resources/views/layouts/app.blade.php` — minimal skeleton, no CDN, no inline styles
- [x] `resources/views/backup.blade.php` — Alpine `x-data` component, all state reactive, async methods
- [x] `resources/views/restore.blade.php` — Alpine `x-data` component, dynamic group/chunk rendering
- [x] JSZip remains on CDN (consumer can install via npm if preferred)
- [x] No `@tailwindcss/browser` CDN script shipped; consumer app bundles Tailwind + Alpine
- [x] `README.md` updated with consumer build setup instructions
- [x] Test expectations updated for 500 (not 400) on zip-not-found; `downloable` → `downloadable` typo fixed
- [x] 22 Pest tests passing, PHPStan 0 errors, Pint clean

## Verification

- Run `composer test` — 22+ Pest tests
- Run `composer analyse` — PHPStan level 5
- Run `vendor/bin/pint` — code style
