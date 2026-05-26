<?php

namespace Database\Seeders;

use App\Models\IsoClause;
use Illuminate\Database\Seeder;

class IsoClauseSeeder extends Seeder
{
    /**
     * Seed klausul ISO 17025:2017 untuk lab Geomin.
     */
    public function run(): void
    {
        $clauses = [
            // Persyaratan Umum
            ['4', 'Persyaratan Umum'],
            ['4.1', 'Ketidakberpihakan'],
            ['4.2', 'Kerahasiaan'],

            // Persyaratan Struktural
            ['5', 'Persyaratan Struktural'],

            // Persyaratan Sumber Daya
            ['6', 'Persyaratan Sumber Daya'],
            ['6.1', 'Umum'],
            ['6.2', 'Personil'],
            ['6.3', 'Fasilitas dan Kondisi Lingkungan'],
            ['6.4', 'Peralatan'],
            ['6.5', 'Ketertelusuran Metrologi'],
            ['6.6', 'Produk dan Layanan yang Disediakan Secara Eksternal'],

            // Persyaratan Proses
            ['7', 'Persyaratan Proses'],
            ['7.1', 'Kaji Ulang Permintaan Tender dan Kontrak'],
            ['7.2', 'Seleksi, Verifikasi dan Validasi Metode'],
            ['7.3', 'Seleksi dan Verifikasi Metode'],
            ['7.4', 'Validasi Metode'],
            ['7.5', 'Pengambilan Sampel'],
            ['7.6', 'Evaluasi Ketidakpastian Pengukuran'],
            ['7.7', 'Memastikan Keabsahan Hasil'],
            ['7.8', 'Pelaporan Hasil'],
            ['7.8.1', 'Umum'],
            ['7.8.2', 'Persyaratan Umum Untuk Laporan (Uji, Kalibrasi dan Pengambilan Sampel)'],
            ['7.8.3', 'Persyaratan Khusus untuk Laporan Pengujian'],
            ['7.8.4', 'Persyaratan Khusus untuk Sertifikat Kalibrasi'],
            ['7.8.5', 'Pelaporan Sampel – Persyaratan Khusus'],
            ['7.8.6', 'Pernyataan Pelaporan Kesesuaian'],
            ['7.8.7', 'Melaporkan Pendapat dan Interpretasi'],
            ['7.8.8', 'Amandemen Laporan'],
            ['7.9', 'Keluhan'],
            ['7.10', 'Pekerjaan Tidak Sesuai'],
            ['7.11', 'Pengendalian Pengelolaan Data dan Informasi'],

            // Persyaratan Sistem Manajemen
            ['8', 'Persyaratan Sistem Manajemen'],
            ['8.1', 'Pilihan'],
            ['8.1.1', 'Umum'],
            ['8.1.2', 'Pilihan A'],
            ['8.1.3', 'Pilihan B'],
            ['8.2', 'Dokumentasi Sistem Manajemen (Pilihan A)'],
            ['8.3', 'Pengendalian Dokumen Sistem Manajemen (Pilihan A)'],
            ['8.4', 'Pengendalian Rekaman (Pilihan A)'],
            ['8.5', 'Tindakan untuk Mengatasi Resiko dan Peluang (Pilihan A)'],
            ['8.6', 'Audit Internal (Pilihan A)'],
            ['8.7', 'Kaji Ulang Manajemen (Pilihan A)'],
        ];

        foreach ($clauses as [$clauseNumber, $description]) {
            IsoClause::updateOrCreate(
                ['clause_number' => $clauseNumber],
                ['description' => $description],
            );
        }

        $this->command->info('Seeded ' . count($clauses) . ' ISO 17025:2017 clauses.');
    }
}
