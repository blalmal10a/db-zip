<?php

use Blalmal10a\DbZip\Http\Controllers\BackupController;
use Blalmal10a\DbZip\Http\Controllers\RestoreController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web'])
    ->group(function () {
        Route::get('/backup', [BackupController::class, 'index']);
        Route::get('/backup/tables', [BackupController::class, 'getTables']);
        Route::post('/backup/export', [BackupController::class, 'backupTable']);
        Route::post('/backup/zip', [BackupController::class, 'zipFolder']);
        Route::get('/backup/download/{fileName}', [BackupController::class, 'download']);
        Route::delete('/delete-zip-file-by-name', [BackupController::class, 'deleteZipByFileName']);
        Route::get('/restore', [RestoreController::class, 'index']);
        Route::post('/backup/restore', [RestoreController::class, 'restoreTable']);
    });
