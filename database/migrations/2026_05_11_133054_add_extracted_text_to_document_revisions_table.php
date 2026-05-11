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
    Schema::table('document_revisions', function (Blueprint $table) {
        $table->longText('extracted_text')->nullable()->after('word_file_path');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_revisions', function (Blueprint $table) {
            //
        });
    }
};
