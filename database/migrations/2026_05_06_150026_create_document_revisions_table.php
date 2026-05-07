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
        Schema::create('document_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('revision_number'); // Contoh: '00', '01'
            $table->string('file_path');
            $table->string('status')->default('Draft'); // Draft, In_Review, Approved, Published, Obsolete
            $table->text('change_summary')->nullable();
            $table->string('qr_token')->unique()->nullable();
            // Menyimpan ID uploader dari database HRIS 'lab'
            $table->unsignedBigInteger('uploader_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_revisions');
    }
};
