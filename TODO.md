# TODO

## CRITICAL — Fix Immediately

- [ ] **composer.json#L22**: Move `orchestra/testbench` from `require` to `require-dev` — currently installs in production.
- [ ] **routes/db-zip.php#L7**: Routes hardcode `['web']` instead of reading `config('db-zip.middleware_group')` — no auth/role enforcement on any endpoint.
- [ ] **routes/db-zip.php#L7-L17**: `backup-role` middleware registered in ServiceProvider but never applied to routes — dead code.

## HIGH — Security & Integrity

- [ ] **src/DbZip.php#L28**: SQL injection via raw `$tableName` interpolation in `SHOW CREATE TABLE`. Escape backticks.
- [ ] **src/DbZip.php#L216-L223**: Path traversal in `downloadBackup()` — sanitize `$fileName` with `basename()`.
- [ ] **src/Http/Controllers/BackupController.php#L99**: Path traversal in `deleteZipByFileName()` — same fix.
- [ ] **src/Http/Controllers/RestoreController.php#L32-L33**: Unsanitized client filename used for table name — validate/sanitize.
- [ ] **resources/views/restore.blade.php#L295**: CDN JSZip script loaded without `integrity`/`crossorigin` — add SRI hash.
- [ ] **src/Http/Controllers/RestoreController.php#L15**: Remove profane debug comment `// return ['what the fucks'];`.
- [ ] **src/Http/Controllers/BackupController.php#L102**: Remove leftover `logger('here')` debug call.
- [ ] **src/Http/Controllers/BackupController.php#L93**: Leaked exception messages to users — log real error, return generic message.
- [ ] **src/Http/Controllers/RestoreController.php#L44**: Same leak — log, don't expose `$e->getMessage()`.

## HIGH — Data Integrity (Round-trip Corruption)

- [ ] **src/DbZip.php#L67-L76**: Null/empty CSV encoding breaks round-trip. Null → `''`, empty string → `'""'` which restores as literal `""`. Use distinct sentinel values.
- [ ] **src/DbZip.php#L137**: `explode("\n", ...)` fails on Windows `\r\n`. Use `preg_split('/\r\n|\n\r|\n/', ...)`.
- [ ] **src/DbZip.php#L141**: `preg_replace('/[\x00-\x1F\x80-\xFF]/', ...)` strips all non-ASCII bytes (UTF-8 corruption). Only strip BOM specifically.
- [ ] **src/DbZip.php#L161-L197**: CSV parser can't handle newlines within quoted fields — use `league/csv` or state-machine parser.

## HIGH — Dependencies & Config

- [ ] **composer.json**: Add `"ext-zip": "*"` to `require`.
- [ ] **composer.json**: Add `"spatie/laravel-permission"` to `suggest` or as soft dependency (with fallback in middleware).
- [ ] **config/db-zip.php#L4**: Add `env('DBZIP_BACKUP_PATH', 'backup')` wrapper to match docs.
- [ ] **config/db-zip.php#L6**: Add `env('DBZIP_ZIP_PATH', 'zip')` wrapper.
- [ ] **src/Http/Middleware/CheckBackupRole.php#L21**: Guard `hasAnyRole()` call or use interface/contract to avoid fatal error when Spatie is absent.
- [ ] **src/DbZip.php#L28**: `DB::connection()->getDatabaseName()` uses default connection, ignoring `$connection` param. Use `DB::connection($connection)->getDatabaseName()`.

## MEDIUM — REST & Route Hygiene

- [ ] **routes/db-zip.php**: Name all routes (`->name('backup.index')`, etc.) so internal URL generation works.
- [ ] **routes/db-zip.php#L14**: Non-RESTful `DELETE /delete-zip-file-by-name` → `DELETE /backup/{fileName}`.
- [ ] **src/Http/Controllers/BackupController.php**: `GET /backup/tables` writes schema files (side effect). Move to POST.
- [ ] **src/Http/Controllers/BackupController.php#L80**: Exception message parsing for status codes — use custom exception classes instead.
- [ ] **All controllers**: Add explicit return types (`: View`, `: JsonResponse`, `: Response`).

## MEDIUM — Missing Features

- [ ] **database/migrations/create_db_zip_table.php.stub**: Currently dead code — no code writes to `db_zips`. Either implement metadata tracking or remove migration.
- [ ] **src/DbZipCommand.php**: Stub that echoes "All done". Add real commands: `db-zip:backup {table}`, `db-zip:list`, `db-zip:restore {file}`, `db-zip:delete {name}`.
- [ ] **Add custom exception classes**: `BackupException`, `RestoreException`, `TableNotFoundException`, `InvalidBackupFileException`.
- [ ] **Add events**: `BackupStarted`, `BackupCompleted`, `BackupFailed`, `RestoreStarted`, `RestoreCompleted`.

## LOW — Code Quality

- [ ] Add `declare(strict_types=1)` to all PHP files.
- [ ] **Migration stub**: Add `: void` return types and `down()` method.
- [ ] **phpstan.neon.dist**: Bump from level 5 to level 6+.
- [ ] **tests/ExampleTest.php**: Remove skeleton placeholder test.
- [ ] **tests/Feature/BackupTest.php**: All tests use `$this->withoutMiddleware()` — zero coverage of auth/role layer. Add middleware tests.
- [ ] **tests/DbZipTest.php**: Add CSV round-trip fidelity test.
- [ ] **tests/DbZipTest.php**: Add Windows line endings test.
- [ ] **tests/DbZipTest.php**: Fix typo `downloable` → `downloadable`.
- [ ] **src/DbZip.php**: Chunk size (400) and batch size (2000) hardcoded — make configurable.
