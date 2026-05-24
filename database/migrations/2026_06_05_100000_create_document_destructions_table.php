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
        Schema::create('document_destructions', function (Blueprint $table) {
            $table->id();
            $table->string('bapd_number')->unique();
            $table->foreignId('document_id')->constrained('documents')->restrictOnDelete();
            $table->unsignedBigInteger('proposer_id');
            $table->text('reason');
            $table->string('method');
            $table->string('status')->default('Pending');
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_destructions');
    }
};
