<?php

namespace Blalmal10a\DbZip\Http\Controllers;

use Blalmal10a\DbZip\DbZip;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class RestoreController extends Controller
{
    public function __construct(protected DbZip $dbZip) {}

    public function restoreTable(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
            'table_sql' => 'nullable|string',
        ]);

        set_time_limit(300);
        ini_set('max_input_time', 300);

        $file = $request->file('file');
        $tableSQL = $request->input('table_sql');
        $tableName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        try {
            $csvContent = file_get_contents($file->getRealPath());
            $this->dbZip->restoreTable($tableName, $csvContent, $tableSQL);

            return response()->json([
                'success' => true,
                'message' => "Table '{$tableName}' successfully restored.",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => "Restore failed for '{$tableName}': {$e->getMessage()}",
            ], 500);
        }
    }
}
