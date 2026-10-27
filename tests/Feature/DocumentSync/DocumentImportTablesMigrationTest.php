<?php

namespace Tests\Feature\DocumentSync;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DocumentImportTablesMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('document_import_items');
        Schema::dropIfExists('document_import_batches');
        \Illuminate\Support\Facades\DB::table('migrations')
            ->where('migration', 'like', '%create_document_import_tables%')
            ->delete();

        // Pastikan migration dijalankan di database testing
        Artisan::call('migrate', ['--database' => 'mysql']);
    }

    public function test_migration_creates_staging_tables_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('document_import_batches'), 'Tabel document_import_batches tidak ditemukan.');
        $this->assertTrue(Schema::hasTable('document_import_items'), 'Tabel document_import_items tidak ditemukan.');

        // Verifikasi kolom penting document_import_batches
        $this->assertTrue(Schema::hasColumns('document_import_batches', [
            'id',
            'batch_number',
            'scanned_by',
            'source_directory',
            'status',
            'total_files_scanned',
            'total_matched',
            'total_installed',
            'total_skipped',
            'total_failed',
            'confirmed_at',
            'completed_at',
            'created_at',
            'updated_at',
        ]));

        // Verifikasi kolom penting document_import_items
        $this->assertTrue(Schema::hasColumns('document_import_items', [
            'id',
            'batch_id',
            'source_filename',
            'source_relative_path',
            'file_extension',
            'file_size_bytes',
            'file_hash',
            'parsed_document_number',
            'parsed_revision_number',
            'role_hint',
            'matched_document_id',
            'matched_revision_id',
            'target_slot',
            'slot_current_status',
            'match_status',
            'decision',
            'is_selected',
            'install_status',
            'installed_path',
            'error_message',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_migration_can_be_rolled_back_and_re_run(): void
    {
        // Rollback migration staging (1 step)
        $exitCode = Artisan::call('migrate:rollback', [
            '--step' => 1,
            '--database' => 'mysql',
        ]);
        $this->assertSame(0, $exitCode);

        $this->assertFalse(Schema::hasTable('document_import_items'));
        $this->assertFalse(Schema::hasTable('document_import_batches'));

        // Migrasi ulang
        $reRunExit = Artisan::call('migrate', ['--database' => 'mysql']);
        $this->assertSame(0, $reRunExit);

        $this->assertTrue(Schema::hasTable('document_import_batches'));
        $this->assertTrue(Schema::hasTable('document_import_items'));
    }
}
