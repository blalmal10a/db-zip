<?php

// namespace App\Http\Controllers;

// use Illuminate\Database\Schema\Blueprint;
// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Facades\Schema;
// use Illuminate\Support\Facades\File;
// use Illuminate\Support\Facades\Storage;
// use ZipArchive;

// class BackupController extends Controller
// {

//     public function getTables()
//     {

//         $tables = Schema::getTables(env('DB_DATABASE'));
//         $output = [];

//         foreach ($tables as $table) {
//             $tableName = $table['name'];

//             $createStatement = DB::select("SHOW CREATE TABLE `{$tableName}`");
//             $rawCreateSql = $createStatement[0]->{'Create Table'};

//             $cleanSql = "DROP TABLE IF EXISTS `{$tableName}`;\n" . $rawCreateSql . ";";

//             array_push($output, $cleanSql);
//         }
//         $dateFolder = request('timestamp') ?? now();
//         $directoryPath = public_path("storage/backup/{$dateFolder}");

//         if (!File::isDirectory($directoryPath)) {
//             File::makeDirectory($directoryPath, 0755, true, true);
//         }

//         $filePath = "{$directoryPath}/tables.json";
//         // Save the structured schema to your JSON file
//         File::put($filePath, json_encode($output, JSON_PRETTY_PRINT));
//         $files = Storage::disk('public')->allFiles('zip');

//         $formattedFiles = array_map(function ($file) {
//             return 'public/storage/' . $file;
//         }, $files);

//         // return response()->json($tables);
//         return response()->json([
//             'tables' => $tables,
//             'status' => 'success',
//             'count' => count($formattedFiles),
//             'files' => $formattedFiles
//         ]);
//     }

//     public function backupTable(Request $request)
//     {
//         set_time_limit(300);

//         ini_set('max_input_time', 300);

//         header("Connection: keep-alive");

//         $tableName = $request->input('table-name');

//         if (!$tableName || !Schema::hasTable($tableName)) {
//             return response()->json(['error' => "Table '{$tableName}' not found."], 400);
//         }

//         $dateFolder = request('timestamp') ?? now();
//         $directoryPath = public_path("storage/backup/{$dateFolder}");

//         if (!File::isDirectory($directoryPath)) {
//             File::makeDirectory($directoryPath, 0755, true, true);
//         }

//         $filePath = "{$directoryPath}/{$tableName}.csv";
//         $fileHandle = fopen($filePath, 'w');

//         $headers = Schema::getColumnListing($tableName);
//         fputcsv($fileHandle, $headers);

//         DB::table($tableName)
//             ->orderBy($headers[0] ?? 'id')
//             ->chunk(10000, function ($rows) use ($fileHandle) {
//                 foreach ($rows as $row) {
//                     $arrayRow = array_values((array) $row);

//                     // Format each item before putting it into the CSV
//                     $sanitizedRow = array_map(function ($value) {
//                         if ($value === null) {
//                             return ''; // Will output as ,,
//                         }
//                         if ($value === '') {
//                             return '""';
//                         }
//                         return $value;
//                     }, $arrayRow);
//                     fputcsv($fileHandle, array_values((array) $sanitizedRow));
//                 }
//             });

//         fclose($fileHandle);

//         return response()->json([
//             'success' => true,
//             'message' => "Table '{$tableName}' saved to backup/{$dateFolder}/",
//         ]);
//     }

//     public function zipFolder()
//     {
//         $timestamp = request('timestamp');
//         $savedPath = public_path("storage/zip/{$timestamp}.zip");
//         $directoryPath = public_path("storage/backup/{$timestamp}");

//         if (!File::exists($directoryPath) || !is_dir($directoryPath)) {
//             return response()->json(['error' => 'Backup directory not found.'], 400);
//         }

//         $targetDir = dirname($savedPath);
//         File::ensureDirectoryExists($targetDir);

//         $zipFile = new ZipArchive();

//         if ($zipFile->open($savedPath, ZipArchive::CREATE) !== true) {
//             return response()->json(['error' => 'Failed to create zip file.'], 500);
//         }

//         $files = File::files($directoryPath);

//         foreach ($files as $file) {
//             $zipFile->addFile($file->getPathname(), $file->getFilename());
//         }

//         $zipFile->close();
//         // delete the existing folder
//         clearstatcache();
//         File::deleteDirectory($directoryPath);
//         if (File::exists($directoryPath)) {
//             File::deleteDirectory("storage/backup");
//         }

//         if (File::exists(public_path("storage/backup"))) {
//             logger('delte status: ' . File::deleteDirectory("storage/backup"));
//         }
//         return response()->json([
//             'success' => true,
//             'message' => "Backup saved to zip/{$timestamp}.zip",
//             'url' => "/zip/{$timestamp}.zip"
//         ]);
//     }

//     public function restoreTable(Request $request)
//     {
//         // 1. Validate incoming payload
//         $request->validate([
//             'file'       => 'required|file|mimes:csv,txt',
//             'table_sql'  => 'nullable|string',
//         ]);

//         set_time_limit(300);
//         ini_set('max_input_time', 300);

//         $file      = $request->file('file');
//         $tableSQL  = $request->input('table_sql'); // Raw CREATE TABLE SQL string from table.json

//         $tableName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

//         // 2. If SQL was provided, drop and recreate the table from the original schema
//         if (!empty($tableSQL)) {
//             try {
//                 Schema::disableForeignKeyConstraints();
//                 DB::unprepared($tableSQL); // Executes the DROP TABLE IF EXISTS + CREATE TABLE block
//                 Schema::enableForeignKeyConstraints();
//             } catch (\Exception $e) {
//                 return response()->json([
//                     'error' => "Failed to recreate table '{$tableName}' from schema: {$e->getMessage()}"
//                 ], 500);
//             }
//         } else {
//             // Fallback: table must already exist; just truncate it
//             if (!Schema::hasTable($tableName)) {
//                 return response()->json([
//                     'error' => "Table '{$tableName}' does not exist and no schema SQL was provided."
//                 ], 400);
//             }

//             Schema::disableForeignKeyConstraints();
//             DB::table($tableName)->truncate();
//             Schema::enableForeignKeyConstraints();
//         }

//         // 3. Open and validate the CSV file
//         $filePath = $file->getRealPath();
//         if (($handle = fopen($filePath, 'r')) === false) {
//             return response()->json(['error' => "Failed to read the uploaded file for '{$tableName}'."], 400);
//         }

//         $headers = fgetcsv($handle);
//         if (!$headers) {
//             fclose($handle);
//             return response()->json(['error' => "The CSV file for table '{$tableName}' is empty or invalid."], 400);
//         }

//         // Strip BOM and hidden characters from header row
//         $headers[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $headers[0]);

//         // 4. Detect JSON columns from the live table schema
//         $jsonColumns = [];
//         foreach ($headers as $column) {
//             try {
//                 if (Schema::getColumnType($tableName, $column) === 'json') {
//                     $jsonColumns[] = $column;
//                 }
//             } catch (\Exception $e) {
//                 continue;
//             }
//         }

//         // 5. Stream and batch insert rows
//         $batch     = [];
//         $batchSize = 2000;

//         Schema::disableForeignKeyConstraints();
//         try {
//             DB::beginTransaction();

//             while (($row = fgetcsv($handle)) !== false) {
//                 if (count($headers) !== count($row)) {
//                     continue; // Skip malformed rows
//                 }

//                 $rowData = array_combine($headers, $row);

//                 foreach ($rowData as $key => $value) {
//                     $trimmedValue = trim($value);

//                     if (in_array($key, $jsonColumns)) {
//                         if ($trimmedValue === '') {
//                             $rowData[$key] = null;
//                         } else {
//                             json_decode($trimmedValue);
//                             $rowData[$key] = json_last_error() === JSON_ERROR_NONE
//                                 ? $trimmedValue
//                                 : json_encode($trimmedValue);
//                         }
//                     } else {
//                         $rowData[$key] = $trimmedValue === '' ? null : $trimmedValue;
//                     }
//                 }

//                 $batch[] = $rowData;

//                 if (count($batch) >= $batchSize) {
//                     DB::table($tableName)->insert($batch);
//                     $batch = [];
//                 }
//             }

//             if (!empty($batch)) {
//                 DB::table($tableName)->insert($batch);
//             }

//             DB::commit();
//         } catch (\Exception $e) {
//             DB::rollBack();
//             fclose($handle);
//             Schema::enableForeignKeyConstraints();
//             return response()->json(['error' => "Insertion failed for '{$tableName}': {$e->getMessage()}"], 500);
//         }

//         Schema::enableForeignKeyConstraints();
//         fclose($handle);

//         return response()->json([
//             'success' => true,
//             'message' => "Table '{$tableName}' successfully restored.",
//         ]);
//     }
//     public function deleteZipByFileName(Request $request)
//     {
//         $fileName = $request->input('fileName');
//         $filePath = public_path("storage/zip/{$fileName}.zip");
//         if (File::exists($filePath)) {
//             File::delete($filePath);
//             return response()->json([
//                 'success' => true,
//                 'message' => "Zip file '{$fileName}' deleted successfully.",
//             ]);
//         }
//         return response()->json([
//             'success' => false,
//             'message' => "Zip file '{$fileName}' not found.",
//         ], 400);
//     }
// }
