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
        Schema::create('document_review_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->date('due_date');
            $table->timestamp('panel_notified_at')->nullable();
            $table->timestamp('email_notified_at')->nullable();
            $table->timestamp('overdue_notified_at')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'due_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_review_notifications');
    }
};
