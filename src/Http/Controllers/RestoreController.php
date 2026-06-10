<?php

namespace Blalmal10a\DbZip\Http\Controllers;

use Blalmal10a\DbZip\DbZip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class RestoreController extends Controller
{
    public function __construct(protected DbZip $dbZip) {}

    public function index(): View
    {
        return view('db-zip::restore', [
            'route' => config('db-zip.route'),
        ]);
    }

    public function restoreTable(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
            'table_sql' => 'nullable|string',
        ]);

        if (function_exists('set_time_limit')) {
            set_time_limit(300);
        }

        $file = $request->file('file');
        $tableSQL = $request->input('table_sql');
        $append = $request->boolean('append');

        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $tableName = preg_replace('/[^a-zA-Z0-9_]/', '', preg_replace('/_\d+$/', '', $originalName));

        if (empty($tableName)) {
            return response()->json(['error' => 'Invalid table name derived from file.'], 400);
        }

        try {
            $csvContent = file_get_contents($file->getRealPath());
            $this->dbZip->restoreTable($tableName, $csvContent, $tableSQL, $append);

            return response()->json([
                'success' => true,
                'message' => "Table '{$tableName}' successfully restored.",
            ]);
        } catch (\Exception $e) {
            Log::error('Restore failed: '.$e->getMessage());

            return response()->json([
                'error' => 'Restore failed. Check logs for details.',
            ], 500);
        }
    }
}
