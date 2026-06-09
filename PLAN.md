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
- [x] Pest tests — 18 passing
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

## Verification

- Run `composer test` — 21+ Pest tests
- Run `composer analyse` — PHPStan level 5
- Run `vendor/bin/pint` — code style
