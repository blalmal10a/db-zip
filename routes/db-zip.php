<?php

use Illuminate\Support\Facades\Route;

Route::middleware([...config('db-zip.middleware_group', ['web']), 'backup-role'])
    ->group(function () {
        Route::get(config('db-zip.route.backup_index'), [config('db-zip.controllers.backup'), 'index'])->name('backup.index');
        Route::get(config('db-zip.route.backup_tables'), [config('db-zip.controllers.backup'), 'getTables'])->name('backup.tables');
        Route::post(config('db-zip.route.backup_export'), [config('db-zip.controllers.backup'), 'backupTable'])->name('backup.export');
        Route::post(config('db-zip.route.backup_zip'), [config('db-zip.controllers.backup'), 'zipFolder'])->name('backup.zip');
        Route::get(config('db-zip.route.backup_download'), [config('db-zip.controllers.backup'), 'download'])->name('backup.download');
        Route::delete(config('db-zip.route.backup_delete'), [config('db-zip.controllers.backup'), 'deleteZipByFileName'])->name('backup.delete');
        Route::get(config('db-zip.route.restore_index'), [config('db-zip.controllers.restore'), 'index'])->name('restore.index');
        Route::post(config('db-zip.route.backup_restore'), [config('db-zip.controllers.restore'), 'restoreTable'])->name('backup.restore');
    });
