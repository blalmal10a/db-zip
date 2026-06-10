<?php

use Blalmal10a\DbZip\DbZip;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Config::set('db-zip.backup_path', 'test-backup');
    Config::set('db-zip.zip_path', 'test-zip');

    Schema::create('test_table', function ($table) {
        $table->id();
        $table->string('name');
        $table->string('email')->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('test_table');

    File::deleteDirectory(storage_path('test-backup'));
    File::deleteDirectory(storage_path('test-zip'));
});

it('can list tables and get schema', function () {
    $dbZip = new DbZip;
    $connection = config('database.default');

    $schemas = $dbZip->getTables($connection);

    expect($schemas)->toBeArray();
});

it('can save schema json file', function () {
    $dbZip = new DbZip;

    $path = $dbZip->saveSchemaJson(['DROP TABLE IF EXISTS `test`;'], '12345');

    expect($path)->toContain('test-backup/12345/tables.json');
    expect(File::exists($path))->toBeTrue();
});

it('can export table to csv chunks of 400', function () {
    $rows = [];
    for ($i = 1; $i <= 450; $i++) {
        $rows[] = ['name' => "User {$i}", 'email' => "user{$i}@test.com"];
    }
    DB::table('test_table')->insert($rows);

    $dbZip = new DbZip;
    $files = $dbZip->exportTableToCsv('test_table', '12345');

    expect($files)->toBeArray();
    expect(count($files))->toBe(2);

    expect($files[0])->toContain('test_table_001.csv');
    expect($files[1])->toContain('test_table_002.csv');
    expect(File::exists($files[0]))->toBeTrue();
    expect(File::exists($files[1]))->toBeTrue();

    $content1 = File::get($files[0]);
    expect($content1)->toContain('User 1');
    expect($content1)->toContain('User 400');

    $content2 = File::get($files[1]);
    expect($content2)->toContain('User 401');
    expect($content2)->toContain('User 450');
});

it('can zip backup and clean up csv folder', function () {
    DB::table('test_table')->insert([
        ['name' => 'Alice', 'email' => 'alice@test.com'],
    ]);

    $dbZip = new DbZip;
    $dbZip->saveSchemaJson(['DROP TABLE IF EXISTS `test_table`;'], '12345');
    $dbZip->exportTableToCsv('test_table', '12345');

    $zipPath = $dbZip->zipBackup('12345');

    expect(File::exists($zipPath))->toBeTrue();
    expect(File::isDirectory(storage_path('test-backup/12345')))->toBeFalse();
});

it('can restore table from csv', function () {
    DB::table('test_table')->insert([
        ['name' => 'Alice', 'email' => 'alice@test.com'],
    ]);

    Schema::disableForeignKeyConstraints();
    DB::table('test_table')->truncate();
    Schema::enableForeignKeyConstraints();

    $csvContent = "name,email\nAlice,alice@test.com\nBob,bob@test.com";

    $dbZip = new DbZip;
    $dbZip->restoreTable('test_table', $csvContent);

    $count = DB::table('test_table')->count();
    expect($count)->toBe(2);
});

it('can restore table with schema recreation', function () {
    $sql = "DROP TABLE IF EXISTS `test_table`;\nCREATE TABLE `test_table` ( `id` int auto_increment primary key, `name` varchar(255), `email` varchar(255) )";

    $csvContent = "name,email\nCharlie,charlie@test.com";

    $dbZip = new DbZip;
    $dbZip->restoreTable('test_table', $csvContent, $sql);

    $count = DB::table('test_table')->count();
    expect($count)->toBe(1);

    $row = DB::table('test_table')->first();
    expect($row->name)->toBe('Charlie');
});

it('can append rows without truncating on restore', function () {
    DB::table('test_table')->insert([
        ['name' => 'Existing', 'email' => 'existing@test.com'],
    ]);

    $csvContent = "name,email\nAppended,appended@test.com";

    $dbZip = new DbZip;
    $dbZip->restoreTable('test_table', $csvContent, null, true);

    $count = DB::table('test_table')->count();
    expect($count)->toBe(2);
});

it('can list backups', function () {
    $dbZip = new DbZip;

    $backups = $dbZip->listBackups();

    expect($backups)->toBeArray();
});

it('can delete backup', function () {
    $dbZip = new DbZip;
    $zipPath = storage_path('test-zip');
    File::ensureDirectoryExists($zipPath);
    touch("{$zipPath}/testfile.zip");

    $deleted = $dbZip->deleteBackup('testfile');

    expect($deleted)->toBeTrue();
    expect(File::exists("{$zipPath}/testfile.zip"))->toBeFalse();
});

it('can download backup file', function () {
    $dbZip = new DbZip;
    $zipPath = storage_path('test-zip');
    File::ensureDirectoryExists($zipPath);
    touch("{$zipPath}/downloadable.zip");

    $filePath = $dbZip->downloadBackup('downloadable');

    expect($filePath)->toContain('test-zip/downloadable.zip');
    expect(File::exists($filePath))->toBeTrue();
});
