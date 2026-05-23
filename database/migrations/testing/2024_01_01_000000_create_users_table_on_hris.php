<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration khusus untuk testing environment.
 * Membuat tabel users di koneksi mysql_hris (hris_testing).
 */
return new class extends Migration
{
    protected $connection = 'mysql_hris';

    public function up(): void
    {
        Schema::connection('mysql_hris')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->enum('jenis_kelamin', ['Laki-laki', 'Perempuan'])->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('role', ['admin', 'manajemen', 'personil'])->default('personil');
            $table->enum('jabatan', [
                'analis',
                'preparator',
                'mekanik',
                'admin',
                'manajemen',
                'asisten manager analis',
                'asisten manager preparator',
                'manager',
                'safety officer',
            ])->nullable();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->string('signature_path')->nullable();
            $table->date('tanggal_mulai_bekerja')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_hris')->dropIfExists('users');
    }
};
