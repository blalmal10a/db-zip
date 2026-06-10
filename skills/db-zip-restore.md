# db-zip-restore

Activate this skill when implementing or modifying the database restore flow.

## Workflow

1. **Client-side ZIP parsing** — Use JSZip to read the uploaded ZIP file on the client. Parse `table.json` to extract CREATE TABLE SQL per table. Send each CSV file + its SQL as a POST request to the server.

2. **Schema recreation** — On the server, if `table_sql` is provided, execute `Schema::disableForeignKeyConstraints()`, `DB::unprepared($tableSQL)` (DROP + CREATE), then re-enable FKs. If no SQL, fallback: truncate existing table.

3. **CSV streaming + batch insert** — Read CSV with `fgetcsv`. Detect JSON columns via `Schema::getColumnType()`. Batch insert 2000 rows at a time inside a single transaction.

4. **JSON column handling** — Decode and re-encode JSON values. Empty JSON fields → null.

5. **Error handling** — Wrap in try/catch with `DB::rollBack()` on failure. Return per-file success/error JSON.

## Key patterns

- Always manage foreign key constraints around schema changes.
- Use transactions for batch inserts.
- Handle malformed CSV rows gracefully (skip if column count mismatch).
