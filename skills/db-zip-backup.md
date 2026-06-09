# db-zip-backup

Activate this skill when implementing or modifying the database backup flow.

## Workflow

1. **List tables** — Use `Schema::getTables()` with the database name to introspect all tables. For each table, get its CREATE TABLE SQL via `DB::select("SHOW CREATE TABLE `{table}`")`. Save the full DROP+CREATE SQL array as `tables.json` in the config-defined backup path.

2. **CSV export per table** — Stream rows using `DB::table($name)->orderBy($pk)->chunk(10000, ...)` with `fputcsv`. Store CSV files in `config('db-zip.backup_path')/{timestamp}/`. Handle null → empty string, empty string → escaped `""` for CSV compatibility.

3. **Zip the folder** — Use `ZipArchive` to compress all CSV files + `tables.json` into `config('db-zip.zip_path')/{timestamp}.zip`. Delete the CSV directory after zipping.

4. **List existing backups** — Scan `config('db-zip.zip_path')` directory for `.zip` files.

5. **Delete backup** — Remove a specific zip file by name.

## Key patterns

- All paths resolved via `config('db-zip.*')` → use `Storage::disk('public')->path(...)`.
- Return JSON responses for frontend consumption.
- Use `set_time_limit(300)` for large exports.
