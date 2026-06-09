<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Config::set('db-zip.backup_path', 'test-backup');
    Config::set('db-zip.zip_path', 'test-zip');
});

it('can restore a table from csv upload', function () {
    $this->withoutMiddleware();

    Schema::create('restore_test', function ($table) {
        $table->id();
        $table->string('name');
        $table->string('email')->nullable();
    });

    $csvContent = "name,email\nAlice,alice@test.com\nBob,bob@test.com";
    $file = UploadedFile::fake()->createWithContent('restore_test.csv', $csvContent);

    $response = $this->post('/backup/restore', [
        'file' => $file,
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);
    $response->assertJsonPath('message', "Table 'restore_test' successfully restored.");

    Schema::dropIfExists('restore_test');
});

it('can restore a table with schema sql', function () {
    $this->withoutMiddleware();

    $sql = "DROP TABLE IF EXISTS `restore_test_schema`;\nCREATE TABLE `restore_test_schema` ( `id` int auto_increment primary key, `name` varchar(255) )";

    $csvContent = "name\nCharlie";
    $file = UploadedFile::fake()->createWithContent('restore_test_schema.csv', $csvContent);

    $response = $this->post('/backup/restore', [
        'file' => $file,
        'table_sql' => $sql,
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);

    Schema::dropIfExists('restore_test_schema');
});

it('validates file upload', function () {
    $this->withoutMiddleware();

    $response = $this->post('/backup/restore', []);

    $response->assertStatus(302);
});
