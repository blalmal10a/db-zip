<?php

use Blalmal10a\DbZip\Http\Controllers\BackupController;
use Blalmal10a\DbZip\Http\Controllers\RestoreController;
use Illuminate\Support\Facades\Route;

Route::middleware([...config('db-zip.middleware_group', ['web']), 'backup-role'])
    ->group(function () {
        Route::get('/backup', [BackupController::class, 'index'])->name('backup.index');
        Route::get('/backup/tables', [BackupController::class, 'getTables'])->name('backup.tables');
        Route::post('/backup/export', [BackupController::class, 'backupTable'])->name('backup.export');
        Route::post('/backup/zip', [BackupController::class, 'zipFolder'])->name('backup.zip');
        Route::get('/backup/download/{fileName}', [BackupController::class, 'download'])->name('backup.download');
        Route::delete('/backup/{fileName}', [BackupController::class, 'deleteZipByFileName'])->name('backup.delete');
        Route::get('/restore', [RestoreController::class, 'index'])->name('restore.index');
        Route::post('/backup/restore', [RestoreController::class, 'restoreTable'])->name('backup.restore');
    });
