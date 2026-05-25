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
        Schema::create('quality_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')
                ->constrained('documents')
                ->cascadeOnDelete();
            $table->string('record_name', 255);
            $table->date('record_date');
            $table->string('confidentiality', 50)->default('Biasa');
            $table->text('description')->nullable();
            $table->string('physical_location', 255)->nullable();
            $table->string('file_path', 2048);
            $table->date('expiration_date');
            $table->string('status', 50)->default('Aktif');
            $table->unsignedBigInteger('uploader_id');
            $table->timestamps();

            $table->index(['status', 'expiration_date']);
            $table->index('document_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quality_records');
    }
};
