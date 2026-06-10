<?php

namespace Blalmal10a\DbZip;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use ZipArchive;

class DbZip
{
    public function getTables(string $connection): array
    {
        $databaseName = DB::connection($connection)->getDatabaseName();
        $tables = Schema::getTables($databaseName);
        $output = [];
        $driverName = DB::connection($connection)->getDriverName();

        foreach ($tables as $table) {
            $tableName = $table['name'];
            $escapedTable = str_replace('`', '``', $tableName);

            if ($driverName === 'sqlite') {
                $row = DB::connection($connection)->select("SELECT sql FROM sqlite_master WHERE type='table' AND name=?", [$tableName]);
                if (! empty($row) && ! empty($row[0]->sql)) {
                    $output[] = "DROP TABLE IF EXISTS `{$escapedTable}`;\n".$row[0]->sql.';';
                }
            } else {
                $createStatement = DB::connection($connection)->select("SHOW CREATE TABLE `{$escapedTable}`");
                $rawCreateSql = $createStatement[0]->{'Create Table'};
                $output[] = "DROP TABLE IF EXISTS `{$escapedTable}`;\n".$rawCreateSql.';';
            }
        }

        return $output;
    }

    public function saveSchemaJson(array $schemaData, string $timestamp): string
    {
        $path = $this->getBackupPath($timestamp);
        File::ensureDirectoryExists($path);
        File::put("{$path}/tables.json", json_encode($schemaData, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return "{$path}/tables.json";
    }

    public function exportTableToCsv(string $tableName, string $timestamp): array
    {
        $path = $this->getBackupPath($timestamp);
        File::ensureDirectoryExists($path);

        $headers = Schema::getColumnListing($tableName);
        $pk = $headers[0] ?? 'id';
        $chunkNumber = 0;
        $files = [];

        DB::table($tableName)
            ->orderBy($pk)
            ->chunk(400, function ($rows) use ($path, $tableName, $headers, &$chunkNumber, &$files) {
                $chunkNumber++;
                $suffix = str_pad((string) $chunkNumber, 3, '0', STR_PAD_LEFT);
                $filePath = "{$path}/{$tableName}_{$suffix}.csv";
                $fileHandle = fopen($filePath, 'w');
                if ($fileHandle === false) {
                    throw new \RuntimeException("Failed to create CSV file: {$filePath}");
                }
                fputcsv($fileHandle, $headers);

                foreach ($rows as $row) {
                    $arrayRow = array_values((array) $row);
                    $sanitizedRow = array_map(fn ($value) => $value ?? '', $arrayRow);
                    fputcsv($fileHandle, $sanitizedRow);
                }

                fclose($fileHandle);
                $files[] = $filePath;
            });

        return $files;
    }

    public function zipBackup(string $timestamp): string
    {
        $backupPath = $this->getBackupPath($timestamp);

        if (! File::isDirectory($backupPath)) {
            throw new \RuntimeException("Backup directory for timestamp '{$timestamp}' not found.");
        }

        $zipPath = $this->getZipPath();
        File::ensureDirectoryExists($zipPath);

        $zipFile = "{$zipPath}/{$timestamp}.zip";

        $zip = new ZipArchive;

        if ($zip->open($zipFile, ZipArchive::CREATE) !== true) {
            throw new \RuntimeException("Failed to create zip file: {$zipFile}");
        }

        $files = File::files($backupPath);

        foreach ($files as $file) {
            $zip->addFile($file->getPathname(), $file->getFilename());
        }

        $zip->close();

        File::deleteDirectory($backupPath);

        return $zipFile;
    }

    public function restoreTable(string $tableName, string $csvContent, ?string $tableSQL = null, bool $append = false): void
    {
        if (! $append) {
            if ($tableSQL !== null) {
                Schema::disableForeignKeyConstraints();
                DB::unprepared($tableSQL);
                Schema::enableForeignKeyConstraints();
            } else {
                if (! Schema::hasTable($tableName)) {
                    throw new \RuntimeException("Table '{$tableName}' does not exist and no schema was provided.");
                }

                Schema::disableForeignKeyConstraints();
                DB::table($tableName)->truncate();
                Schema::enableForeignKeyConstraints();
            }
        }

        $lines = preg_split('/\r\n|\n\r|\n/', $csvContent);
        $headerLine = array_shift($lines);
        $headerLine = ltrim($headerLine, "\xEF\xBB\xBF");
        $headers = str_getcsv($headerLine);

        $jsonColumns = [];
        foreach ($headers as $column) {
            try {
                if (Schema::getColumnType($tableName, $column) === 'json') {
                    $jsonColumns[] = $column;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        $batch = [];
        $batchSize = 2000;

        Schema::disableForeignKeyConstraints();
        DB::beginTransaction();

        try {
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }

                $row = str_getcsv($line);
                if (count($headers) !== count($row)) {
                    continue;
                }

                $rowData = array_combine($headers, $row);

                foreach ($rowData as $key => $value) {
                    $trimmedValue = trim($value);

                    if (in_array($key, $jsonColumns, true)) {
                        if ($trimmedValue === '') {
                            $rowData[$key] = null;
                        } else {
                            json_decode($trimmedValue);
                            $rowData[$key] = json_last_error() === JSON_ERROR_NONE
                                ? $trimmedValue
                                : json_encode($trimmedValue);
                        }
                    } else {
                        $rowData[$key] = $trimmedValue === '' ? null : $trimmedValue;
                    }
                }

                $batch[] = $rowData;

                if (count($batch) >= $batchSize) {
                    DB::table($tableName)->insert($batch);
                    $batch = [];
                }
            }

            if (! empty($batch)) {
                DB::table($tableName)->insert($batch);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Schema::enableForeignKeyConstraints();

            throw $e;
        }

        Schema::enableForeignKeyConstraints();
    }

    public function downloadBackup(string $fileName): string
    {
        $zipPath = $this->getZipPath();

        $safeName = basename($fileName);
        $filePath = "{$zipPath}/{$safeName}.zip";
        logger($filePath);
        $realZipPath = realpath($zipPath);
        $realFilePath = realpath($filePath);

        if ($realZipPath === false || $realFilePath === false || ! str_starts_with($realFilePath, $realZipPath)) {
            throw new \RuntimeException("Backup file '{$fileName}.zip' not found.");
        }

        if (! File::exists($filePath)) {
            throw new \RuntimeException("Backup file '{$fileName}.zip' not found.");
        }

        return $filePath;
    }

    public function listBackups(): array
    {
        $zipPath = $this->getZipPath();

        if (! File::isDirectory($zipPath)) {
            return [];
        }

        $files = File::files($zipPath);

        return array_map(function ($file) {
            $name = pathinfo($file->getFilename(), PATHINFO_FILENAME);

            return [
                'name' => $name,
                'filename' => $file->getFilename(),
                'download_url' => route('backup.download', $name, false),
                'size' => $file->getSize(),
                'last_modified' => $file->getMTime(),
            ];
        }, $files);
    }

    public function deleteBackup(string $fileName): bool
    {

        $zipPath = $this->getZipPath();
        $filePath = "{$zipPath}/{$fileName}.zip";

        $realZipPath = realpath($zipPath);
        $realFilePath = realpath($filePath);

        if ($realZipPath === false || $realFilePath === false || ! str_starts_with($realFilePath, $realZipPath)) {
            return false;
        }

        if (File::exists($filePath)) {
            return File::delete($filePath);
        }

        return false;
    }

    protected function getBackupPath(string $timestamp): string
    {
        $backupPath = config('db-zip.backup_path', 'backup');

        return storage_path("app/{$backupPath}/{$timestamp}");
    }

    protected function getZipPath(): string
    {
        $zipPath = config('db-zip.zip_path', 'zip');

        return storage_path("app/{$zipPath}");
    }
}
