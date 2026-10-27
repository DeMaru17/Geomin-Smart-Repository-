<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('document_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number', 64)->unique();
            $table->unsignedBigInteger('scanned_by')->index(); // ID User admin dari database HRIS
            $table->string('source_directory', 255)->default('document-import');
            $table->string('status', 32)->default('Scanning')->index(); // Scanning, Preview_Ready, Processing, Completed, Cancelled
            $table->unsignedInteger('total_files_scanned')->default(0);
            $table->unsignedInteger('total_matched')->default(0);
            $table->unsignedInteger('total_installed')->default(0);
            $table->unsignedInteger('total_skipped')->default(0);
            $table->unsignedInteger('total_failed')->default(0);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('document_import_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')
                ->constrained('document_import_batches')
                ->cascadeOnDelete();
            $table->string('source_filename', 255);
            $table->string('source_relative_path', 500);
            $table->string('file_extension', 16);
            $table->unsignedBigInteger('file_size_bytes');
            $table->string('file_hash', 64)->index(); // SHA-256 hash sumber

            // Hasil parsing nama file
            $table->string('parsed_document_number', 255)->nullable();
            $table->string('parsed_revision_number', 32)->nullable();
            $table->string('role_hint', 32)->nullable(); // blanko, master, dll.

            // Target pencocokan ke katalog GSR
            $table->foreignId('matched_document_id')
                ->nullable()
                ->constrained('documents')
                ->nullOnDelete();
            $table->foreignId('matched_revision_id')
                ->nullable()
                ->constrained('document_revisions')
                ->nullOnDelete();
            $table->string('target_slot', 32)->nullable(); // 'file_path' | 'word_file_path'

            // Status evaluasi slot dan pencocokan
            $table->string('slot_current_status', 32)->default('UNKNOWN'); // SLOT_EMPTY, SLOT_OCCUPIED_LOCKED, SLOT_SEEDER_PLACEHOLDER, dll.
            $table->string('match_status', 32)->default('PENDING'); // MATCHED, DOC_NOT_FOUND, REV_NOT_FOUND, AMBIGUOUS_MATCH, dll.
            $table->string('decision', 32)->default('PENDING')->index(); // READY_TO_ATTACH, PLACEHOLDER_REPLACEABLE, SLOT_OCCUPIED, CONFLICT_DUPLICATE_SOURCE, dll.

            // Status seleksi admin (Wajib default FALSE)
            $table->boolean('is_selected')->default(false);

            // Status eksekusi pemasangan
            $table->string('install_status', 32)->default('PENDING')->index(); // PENDING, ATTACHED, SKIPPED, FAILED, SOURCE_CHANGED, dll.
            $table->string('installed_path', 500)->nullable();
            $table->text('error_message')->nullable();

            $table->timestamps();

            // Indeks pendukung performa query filter & batching
            $table->index(['batch_id', 'decision'], 'idx_items_batch_decision');
            $table->index(['batch_id', 'is_selected'], 'idx_items_batch_selected');
            $table->index(['matched_document_id', 'matched_revision_id'], 'idx_items_doc_rev');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_import_items');
        Schema::dropIfExists('document_import_batches');
    }
};
