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
        Schema::create('document_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->date('review_date');
            $table->unsignedBigInteger('reviewer_id');
            $table->string('evaluation_status', 255);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'review_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_reviews');
    }
};
