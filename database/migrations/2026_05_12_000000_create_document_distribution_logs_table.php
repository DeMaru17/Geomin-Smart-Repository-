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
        Schema::create('document_distribution_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_revision_id')->constrained('document_revisions')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('recipient_name', 255)->nullable();
            $table->string('purpose', 255)->nullable();
            $table->timestamp('accessed_at');
            $table->boolean('is_qr_access')->default(false);
            $table->string('action', 50);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_distribution_logs');
    }
};
