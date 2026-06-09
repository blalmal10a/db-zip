<?php

namespace Blalmal10a\DbZip\Http\Controllers;

use Blalmal10a\DbZip\DbZip;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;

class BackupController extends Controller
{
    public function __construct(protected DbZip $dbZip) {}

    public function index()
    {
        return view('db-zip::backup');
    }

    public function getTables(Request $request)
    {
        set_time_limit(300);
        ini_set('max_input_time', 300);

        // 1. Get the default connection name (e.g., 'mysql')
        $connection = config('database.default');

        // 2. Fetch the actual database name for that connection
        $databaseName = config("database.connections.{$connection}.database");

        $timestamp = $request->input('timestamp', now()->timestamp);

        // If your dbZip service expects the connection name, keep passing $connection here
        $schemaData = $this->dbZip->getTables($connection);
        $this->dbZip->saveSchemaJson($schemaData, $timestamp);

        // 3. FIX: Pass the actual database name to getTables()
        $tables = Schema::getTables(env('DB_DATABASE'));

        $backups = $this->dbZip->listBackups();

        return response()->json([
            'tables' => $tables,
            'status' => 'success',
            'count' => count($backups),
            'files' => array_column($backups, 'path'),
        ]);
    }

    public function backupTable(Request $request)
    {
        set_time_limit(300);
        ini_set('max_input_time', 300);

        $tableName = $request->input('table-name');

        if (! $tableName || ! Schema::hasTable($tableName)) {
            return response()->json(['error' => "Table '{$tableName}' not found."], 400);
        }

        $timestamp = $request->input('timestamp', now()->timestamp);

        $this->dbZip->exportTableToCsv($tableName, $timestamp);

        return response()->json([
            'success' => true,
            'message' => "Table '{$tableName}' saved to backup/{$timestamp}/",
        ]);
    }

    public function zipFolder(Request $request)
    {
        $timestamp = $request->input('timestamp');

        if (! $timestamp) {
            return response()->json(['error' => 'Timestamp is required.'], 400);
        }

        try {
            $zipPath = $this->dbZip->zipBackup($timestamp);

            return response()->json([
                'success' => true,
                'message' => "Backup saved to zip/{$timestamp}.zip",
                'url' => "/storage/{$timestamp}.zip",
            ]);
        } catch (\RuntimeException $e) {
            $status = str_contains($e->getMessage(), 'not found') ? 400 : 500;

            return response()->json(['error' => $e->getMessage()], $status);
        }
    }

    public function deleteZipByFileName(Request $request)
    {
        $fileName = $request->input('fileName');

        $deleted = $this->dbZip->deleteBackup($fileName);

        if ($deleted) {
            return response()->json([
                'success' => true,
                'message' => "Zip file '{$fileName}' deleted successfully.",
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => "Zip file '{$fileName}' not found.",
        ], 400);
    }
}
