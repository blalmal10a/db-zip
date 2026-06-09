# db-zip Implementation Plan

## Done

- [x] MCP: `laravel-boost` configured in `opencode.json`
- [x] Skills: `skills/db-zip-backup.md`, `skills/db-zip-restore.md`, `skills/db-zip-config.md`, `skills/db-zip-role-access.md`
- [x] Plugins installed: `oh-my-opencode`, `opencode-notify`, `opencode-worktree`, `opencode-conductor`
- [x] `opencode.json` updated with plugin list

## Todo

- [ ] Populate `config/db-zip.php` with backup paths, roles, middleware config
- [ ] Implement `src/DbZip.php` core class
- [ ] Create `src/Http/Controllers/BackupController.php`
- [ ] Create `src/Http/Controllers/RestoreController.php`
- [ ] Create `src/Http/Middleware/CheckBackupRole.php`
- [ ] Create `routes/db-zip.php` with config-driven middleware
- [ ] Register routes + middleware in `DbZipServiceProvider.php`
- [ ] Adapt sample views into `resources/views/` with extendable layout
- [ ] Update migration stub with backup metadata columns
- [ ] Write Pest tests for backup and restore flows
- [ ] Run verification (tests, phpstan, pint)
