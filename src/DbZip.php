<?php

namespace Blalmal10a\DbZip;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class DbZip
{
    public function getTables(string $connection): array
    {
        $databaseName = DB::connection()->getDatabaseName();
        $tables = Schema::getTables($databaseName);
        $output = [];
        $driverName = DB::connection($connection)->getDriverName();

        foreach ($tables as $table) {
            $tableName = $table['name'];

            if ($driverName === 'sqlite') {
                $row = DB::connection($connection)->select("SELECT sql FROM sqlite_master WHERE type='table' AND name=?", [$tableName]);
                if (! empty($row[0]->sql)) {
                    $output[] = "DROP TABLE IF EXISTS `{$tableName}`;\n" . $row[0]->sql . ';';
                }
            } else {
                $createStatement = DB::connection($connection)->select("SHOW CREATE TABLE `{$tableName}`");
                $rawCreateSql = $createStatement[0]->{'Create Table'};
                $output[] = "DROP TABLE IF EXISTS `{$tableName}`;\n" . $rawCreateSql . ';';
            }
        }

        return $output;
    }

    public function saveSchemaJson(array $schemaData, string $timestamp): string
    {
        $path = $this->getBackupPath($timestamp);
        File::ensureDirectoryExists($path);
        File::put("{$path}/tables.json", json_encode($schemaData, JSON_PRETTY_PRINT));

        return "{$path}/tables.json";
    }

    public function exportTableToCsv(string $tableName, string $timestamp): string
    {
        $path = $this->getBackupPath($timestamp);
        File::ensureDirectoryExists($path);

        $filePath = "{$path}/{$tableName}.csv";
        $fileHandle = fopen($filePath, 'w');

        $headers = Schema::getColumnListing($tableName);
        fputcsv($fileHandle, $headers);

        $pk = $headers[0] ?? 'id';

        DB::table($tableName)
            ->orderBy($pk)
            ->chunk(10000, function ($rows) use ($fileHandle) {
                foreach ($rows as $row) {
                    $arrayRow = array_values((array) $row);
                    $sanitizedRow = array_map(function ($value) {
                        if ($value === null) {
                            return '';
                        }
                        if ($value === '') {
                            return '""';
                        }

                        return $value;
                    }, $arrayRow);
                    fputcsv($fileHandle, $sanitizedRow);
                }
            });

        fclose($fileHandle);

        return $filePath;
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

        // File::deleteDirectory($backupPath);

        return $zipFile;
    }

    public function restoreTable(string $tableName, string $csvContent, ?string $tableSQL = null): void
    {
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

        $rows = explode("\n", trim($csvContent));
        $headerLine = array_shift($rows);
        $headers = str_getcsv($headerLine);

        $headers[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $headers[0]);

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
            foreach ($rows as $line) {
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

                    if (in_array($key, $jsonColumns)) {
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

    public function listBackups(): array
    {
        $zipPath = $this->getZipPath();
        $disk = Storage::disk('public');

        if (! $disk->exists($zipPath)) {
            return [];
        }

        $files = $disk->files($zipPath);

        return array_map(function ($file) {
            return [
                'path' => "public/storage/{$file}",
                'name' => pathinfo($file, PATHINFO_FILENAME),
                'url' => "/storage/{$file}",
            ];
        }, $files);
    }

    public function deleteBackup(string $fileName): bool
    {
        return false;
        $zipPath = $this->getZipPath();
        $filePath = "{$zipPath}/{$fileName}.zip";

        if (File::exists($filePath)) {
            return File::delete($filePath);
        }

        return false;
    }

    protected function getBackupPath(string $timestamp): string
    {
        $backupPath = config('db-zip.backup_path', 'backup');
        return public_path("storage/{$backupPath}");
        // return storage_path("app/public/{$backupPath}/{$timestamp}");
    }

    protected function getZipPath(): string
    {
        $zipPath = config('db-zip.zip_path', 'zip');
        return public_path("storage/{$zipPath}");
        // return storage_path("app/public/{$zipPath}");
    }
}
