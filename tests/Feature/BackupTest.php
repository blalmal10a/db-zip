<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Config::set('db-zip.backup_path', 'test-backup');
    Config::set('db-zip.zip_path', 'test-zip');
    Config::set('db-zip.middleware_group', ['web', 'auth']);

    Schema::create('backup_test_table', function ($table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('backup_test_table');

    File::deleteDirectory(storage_path('app/public/test-backup'));
    File::deleteDirectory(storage_path('app/public/test-zip'));
});

it('can get tables list', function () {
    $this->withoutMiddleware();

    $response = $this->getJson('/backup/tables?timestamp='.now()->timestamp);

    $response->assertOk();
    $response->assertJsonStructure([
        'tables',
        'status',
        'files',
    ]);
});

it('can export a table', function () {
    $this->withoutMiddleware();

    DB::table('backup_test_table')->insert(['name' => 'test']);

    $response = $this->postJson('/backup/export?table-name=backup_test_table&timestamp='.now()->timestamp);

    $response->assertOk();
    $response->assertJson([
        'success' => true,
    ]);
});

it('returns 400 for non-existent table', function () {
    $this->withoutMiddleware();

    $response = $this->postJson('/backup/export?table-name=nonexistent_table&timestamp='.now()->timestamp);

    $response->assertStatus(400);
    $response->assertJsonStructure(['error']);
});

it('can zip backup folder', function () {
    $this->withoutMiddleware();

    $timestamp = now()->timestamp;
    DB::table('backup_test_table')->insert(['name' => 'test']);
    $this->postJson('/backup/export?table-name=backup_test_table&timestamp='.$timestamp);

    $response = $this->postJson('/backup/zip?timestamp='.$timestamp);

    $response->assertOk();
    $response->assertJson(['success' => true]);
});

it('returns 400 for zipping non-existent backup', function () {
    $this->withoutMiddleware();

    $response = $this->postJson('/backup/zip?timestamp=invalid_timestamp');

    $response->assertStatus(400);
});
