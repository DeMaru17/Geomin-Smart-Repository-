<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentRevision;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class DocumentSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed daftar induk dokumen Laboratorium berdasarkan
     * Form 273.106.202 Rev. 04 - 2026.
     *
     * Format data:
     * [nomor dokumen, judul, kategori, revisi, tanggal revisi, status dokumen]
     *
     * Kategori: PK = Prosedur Kerja, IK = Instruksi Kerja, F = Formulir.
     * Dokumen bertanda OBSOLETE disimpan dengan status Obsolete.
     */
    public function run(): void
    {
        // 1. Pastikan seeder dependensi sudah tersedia
        if (DocumentCategory::count() === 0) {
            $this->command?->info('Menjalankan DocumentCategorySeeder terlebih dahulu...');
            $this->call(DocumentCategorySeeder::class);
        }

        if (Department::count() === 0) {
            $this->command?->info('Menjalankan DepartmentSeeder terlebih dahulu...');
            $this->call(DepartmentSeeder::class);
        }

        $categories = DocumentCategory::pluck('id', 'code')->toArray();
        $departments = Department::pluck('id', 'code')->toArray();

        $categoryMap = [
            'PK' => $categories['PK'] ?? null,
            'IK' => $categories['IK'] ?? null,
            'F'  => $categories['F'] ?? null,
        ];

        foreach ($categoryMap as $code => $id) {
            if (! $id) {
                throw new RuntimeException(
                    "Kategori dokumen dengan kode [{$code}] tidak ditemukan. Pastikan seeder kategori dijalankan terlebih dahulu."
                );
            }
        }

        $departmentMap = [
            '273.101' => $departments['101'] ?? null,
            '273.102' => $departments['102'] ?? null,
            '273.103' => $departments['103'] ?? null,
            '273.104' => $departments['104'] ?? null,
            '273.105' => $departments['105'] ?? null,
            '273.106' => $departments['106'] ?? null,
            '273.107' => $departments['107'] ?? null,
            '273.108' => $departments['108'] ?? null,
        ];

        foreach ($departmentMap as $prefix => $id) {
            if (! $id) {
                throw new RuntimeException(
                    "Departemen untuk prefix [{$prefix}] tidak ditemukan di tabel departments. Pastikan DepartmentSeeder sudah dijalankan."
                );
            }
        }

        // Resolusi ID uploader yang aman (fallback ke user pertama atau ID 1)
        $uploaderId = User::first()?->id ?? 1;
        $documentsData = $this->getDocumentsData();

        DB::transaction(function () use ($documentsData, $categoryMap, $departmentMap, $uploaderId) {
            foreach ($documentsData as [$documentNumber, $rawTitle, $categoryCode, $revisionNumber, $revisionDate, $documentStatus]) {
                // Bersihkan spasi berlebih atau newline (Alt+Enter dari Excel) pada judul
                $title = trim(preg_replace('/\s+/', ' ', $rawTitle));

                $departmentPrefix = substr($documentNumber, 0, 7);
                $departmentId = $departmentMap[$departmentPrefix] ?? null;

                $document = Document::updateOrCreate(
                    ['document_number' => $documentNumber],
                    [
                        'title' => $title,
                        'category_id' => $categoryMap[$categoryCode],
                        'department_id' => $departmentId,
                        'is_external' => false,
                        'publication_year' => null, // Dokumen internal tidak menggunakan publication_year
                        'retention_period_months' => 36,
                        'status' => $documentStatus,
                    ],
                );

                // Jika dokumen berstatus Obsolete, tandai seluruh revisi sebelumnya sebagai Obsolete
                if ($documentStatus === 'Obsolete') {
                    $document->revisions()->update(['status' => 'Obsolete']);
                }

                // Cari atau siapkan instance revisi target
                $revision = DocumentRevision::firstOrNew([
                    'document_id' => $document->id,
                    'revision_number' => $revisionNumber,
                ]);

                $revision->file_path = $revision->file_path
                    ?: 'documents/' . Str::slug($documentNumber) . '_rev' . $revisionNumber . '.pdf';
                $revision->status = $documentStatus === 'Obsolete' ? 'Obsolete' : 'Published';
                $revision->revision_date = $revisionDate;
                $revision->change_summary = 'Revisi ' . $revisionNumber . ' - ' . $title;
                $revision->uploader_id = $revision->uploader_id ?: $uploaderId;

                // Gunakan 16 karakter acak sesuai standar aplikasi dan test invariant
                if (! $revision->qr_token) {
                    $revision->qr_token = Str::random(16);
                }

                $revision->save();

                // Jika dokumen aktif, pastikan revisi lama selain revisi ini berstatus Obsolete
                if ($documentStatus === 'Active') {
                    $document->revisions()
                        ->where('id', '!=', $revision->id)
                        ->whereIn('status', ['Published', 'Terbit'])
                        ->update(['status' => 'Obsolete']);
                }
            }
        });

        $activeCount = collect($documentsData)->where(5, 'Active')->count();
        $obsoleteCount = collect($documentsData)->where(5, 'Obsolete')->count();

        $this->command?->info(
            "DocumentSeeder selesai: {$activeCount} dokumen aktif dan {$obsoleteCount} dokumen obsolete diproses dari Form 273.106.202 Rev. 04 - 2026."
        );
    }

    /**
     * Data bersumber dari Excel Form 273.106.202 Rev. 04 - 2026.
     * Urutan mengikuti kelompok Prosedur Kerja, Instruksi Kerja, Formulir.
     */
    private function getDocumentsData(): array
    {
        return [
            ['273.101', 'Preparasi', 'PK', '15', '2022-03-25', 'Active'],
            ['273.102', 'Analisis', 'PK', '16', '2022-03-28', 'Active'],
            ['273.103', 'Pengendalian Rekaman', 'PK', '10', '2025-11-21', 'Active'],
            ['273.104', 'Audit Internal', 'PK', '08', '2022-03-28', 'Active'],
            ['273.105', 'Kaji Ulang Manajemen', 'PK', '14', '2022-03-28', 'Active'],
            ['273.106', 'Pengendalian Dokumen', 'PK', '11', '2025-11-21', 'Active'],
            ['273.107', 'Kaji ulang permintaan tender kontrak, subkontrak pengujian, kalibrasi', 'PK', '01', '2026-06-18', 'Active'],
            ['273.108', 'Rekrutmen dan Pelatihan', 'PK', '02', '2025-11-21', 'Active'],
            ['273.101.101', 'Penerimaan dan Pengecekan Sampel', 'IK', '09', '2025-10-08', 'Active'],
            ['273.101.102', 'Preparasi Parsial Sampel Emas Soil, Rock dan Core', 'IK', '10', '2025-10-08', 'Active'],
            ['273.101.103', 'Preparasi Total Sampel Batuan', 'IK', '08', '2023-07-03', 'Active'],
            ['273.101.104', 'Preparasi Sampel Endapan Sungai (Stream Sediment)', 'IK', '06', '2020-06-15', 'Active'],
            ['273.101.105', 'Preparasi Sampel Batuan Nikel', 'IK', '09', '2026-09-01', 'Active'],
            ['273.101.106', 'Preparasi Sampel Batuan Bauksit', 'IK', '10', '2026-09-01', 'Active'],
            ['273.101.107', 'Pembuatan QC Preparasi', 'IK', '08', '2024-09-04', 'Active'],
            ['273.101.108', 'Penanganan dan Penyimpanan Standar Acuan', 'IK', '07', '2025-10-30', 'Active'],
            ['273.101.109', 'Pengarsipan Sampel', 'IK', '08', '2026-04-27', 'Active'],
            ['273.101.110', 'Pengoperasian Oven', 'IK', '05', '2018-10-08', 'Active'],
            ['273.101.111', 'Pengoperasian oven kecil', 'IK', '05', '2018-10-08', 'Active'],
            ['273.101.112', 'Pengoperasian jaw crusher', 'IK', '05', '2018-10-08', 'Active'],
            ['273.101.113', 'Pengoperasian pulverizer', 'IK', '06', '2018-10-08', 'Active'],
            ['273.101.114', 'Pengoperasian Dust Collector', 'IK', '05', '2018-10-08', 'Active'],
            ['273.101.115', 'Pengoperasian kompresor', 'IK', '05', '2018-10-08', 'Active'],
            ['273.101.116', 'Pengoperasian alat-alat fire assay', 'IK', '04', '2015-10-30', 'Active'],
            ['273.101.117', 'Pengoperasian mesin press', 'IK', '06', '2024-01-05', 'Active'],
            ['273.101.118', 'Pengoperasian genset perkins', 'IK', '05', '2018-10-08', 'Active'],
            ['273.101.119', 'Perawatan rutin oven', 'IK', '05', '2018-10-08', 'Active'],
            ['273.101.120', 'Perawatan rutin jaw crusher', 'IK', '05', '2018-10-08', 'Active'],
            ['273.101.121', 'Perawatan rutin pulverizer', 'IK', '05', '2018-10-08', 'Active'],
            ['273.101.122', 'Perawatan rutin dust collector', 'IK', '05', '2018-10-08', 'Active'],
            ['273.101.123', 'Perawatan rutin mesin kompressor', 'IK', '05', '2018-10-08', 'Active'],
            ['273.101.124', 'Perawatan rutin alat-alat fire assay', 'IK', '05', '2018-10-08', 'Active'],
            ['273.101.125', 'Perawatan Rutin mesin press', 'IK', '05', '2018-10-08', 'Active'],
            ['273.101.126', 'Perawatan rutin mesin genset', 'IK', '05', '2018-10-08', 'Active'],
            ['273.101.127', 'Preparasi Sample Sayatan Tipis, Sayatan Poles & Doble Poles', 'IK', '05', '2018-10-08', 'Active'],
            ['273.101.128', 'Perawatan Alat Preparasi Petrografi', 'IK', '05', '2018-10-08', 'Active'],
            ['273.101.129', 'Penanganan Limbah (Cair dan Padat)', 'IK', '02', '2018-10-08', 'Active'],
            ['273.101.130', 'Pembuatan Penyimpanan atau Shelter Limbah Sementara', 'IK', '02', '2018-10-08', 'Active'],
            ['273.101.131', 'Pembuatan Alas Furnace Dengan Menggunakan Semen Tahan Api', 'IK', '02', '2018-10-08', 'Active'],
            ['273.101.132', 'Pembuatan Ducting dari Jaw Crusher dan Meja Split + Mixing di Pomalaa', 'IK', '02', '2018-10-08', 'Active'],
            ['273.101.133', 'Pembuatan Heater Furnace Dengan Menggunakan Kawat Nikelin dan Semen Tahan Api', 'IK', '01', '2018-10-08', 'Active'],
            ['273.101.134', 'Press Manual Specac', 'IK', '01', '2018-10-08', 'Active'],
            ['273.101.135', 'Preparasi Sampel Pengapalan', 'IK', '01', '2018-10-08', 'Active'],
            ['273.101.136', 'Preparasi Sampel Pra Produksi', 'IK', '01', '2018-10-08', 'Active'],
            ['273.101.137', 'Preparasi Sampel Produksi', 'IK', '01', '2018-10-08', 'Active'],
            ['273.101.138', 'Pengujian Moisture Content', 'IK', '01', '2018-10-08', 'Active'],
            ['273.101.139', 'Pengujian Moisture Content Sampel Eksplorasi', 'IK', '01', '2018-10-08', 'Active'],
            ['273.101.140', 'Pengiriman Sampel', 'IK', '01', '2018-10-08', 'Active'],
            ['273.101.141', 'Pengoperasian v-mixer', 'IK', '00', '2021-11-23', 'Active'],
            ['273.101.142', 'Alur proses administrasi lab', 'IK', '00', '2023-03-24', 'Active'],
            ['273.101.143', 'Pembuatan Drum Besi Sebagai Penampungan Debu Dari Exhaust Fan', 'IK', '00', '2023-03-24', 'Active'],
            ['273.101.144', 'Pembuangan Sisa Crusher dan Matrix', 'IK', '05', '2018-10-08', 'Active'],
            ['273.102.101', 'Analisis conto base metal metode perchloric (AAS 1)', 'IK', '04', '2015-10-30', 'Active'],
            ['273.102.102', 'Analisis conto base metal metode multi acid (AAS 3)', 'IK', '08', '2024-12-06', 'Active'],
            ['273.102.103', 'Analisis emas metode aqua regia ppm (AAS 5)', 'IK', '04', '2015-10-30', 'Active'],
            ['273.102.104', 'Analisis emas metode aqua regia ppb (AAS 6)', 'IK', '04', '2015-10-30', 'Active'],
            ['273.102.105', 'Analisis Kadar Au (Emas) Dalam Sampel Batuan Dengan Metode Fire Assay-AAS (FA-01)', 'IK', '11', '2026-02-04', 'Active'],
            ['273.102.106', 'Analisis emas ppb metode fire assay - AAS (FA-02)', 'IK', '04', '2015-10-30', 'Active'],
            ['273.102.107', 'Analisis emas ppm metode fire assay - gravimetri (FA-03)', 'IK', '04', '2015-10-30', 'Active'],
            ['273.102.108', 'Analisis Emas Metode Total Fire Assay-AAS (FA-04)', 'IK', '04', '2015-10-30', 'Active'],
            ['273.102.109', 'Analisis Geokimia Metode Aqua Regia dengan ICP OES (ICP 01)', 'IK', '04', '2015-10-30', 'Active'],
            ['273.102.110', 'Analisis Geokimia Metode Multi Acid dengan ICP OES (ICP 02)', 'IK', '09', '2026-06-25', 'Active'],
            ['273.102.111', 'Analisis Bijih Nikel Dengan Metode Press Powder - XRF (XRF01)', 'IK', '07', '2022-03-29', 'Active'],
            ['273.102.112', 'Analisis nikel metode wax - XRF (XRF 03)', 'IK', '05', '2023-12-12', 'Active'],
            ['273.102.113', 'Analisis Bijih Bauksit Dengan Metode Fusion - XRF (XRF02)', 'IK', '10', '2025-10-06', 'Active'],
            ['273.102.114', 'Analisis TSiO2 conto bauksit metode gravimetri (GM 01)', 'IK', '04', '2015-10-30', 'Active'],
            ['273.102.115', 'Analisis Fe2O3 conto bauksit metode volumetri (VM01)', 'IK', '04', '2015-10-30', 'Active'],
            ['273.102.116', 'Analisis TiO2 conto bauksit metode spektrofometer (UVM01)', 'IK', '04', '2015-10-30', 'Active'],
            ['273.102.117', 'Analisis Kadar Reaktif SiO2 Dalam Bijih Bauksit Dengan Metode Gravimetri (GM02)', 'IK', '07', '2025-10-17', 'Active'],
            ['273.102.118', 'Analisis Al2O3 (conto bauksit metode WET)', 'IK', '04', '2015-10-30', 'Active'],
            ['273.102.119', 'Pencucian keramik, mold dan chruicible platina', 'IK', '04', '2015-10-30', 'Active'],
            ['273.102.120', 'Pengoperasian timbangan analitik (satu desimal)', 'IK', '04', '2015-10-30', 'Active'],
            ['273.102.121', 'Pengoperasian timbangan analitik (dua desimal)', 'IK', '04', '2015-10-30', 'Active'],
            ['273.102.122', 'Pengoperasian timbangan analitik (empat desimal)', 'IK', '05', '2022-03-29', 'Active'],
            ['273.102.123', 'Pengoperasian ultra micro balance', 'IK', '04', '2015-10-30', 'Active'],
            ['273.102.124', 'Pengoperasian hotplate dan ruang asam', 'IK', '05', '2026-06-18', 'Active'],
            ['273.102.125', 'Pengoperasian AAS 240 FS dan AAS 280 FS Varian', 'IK', '04', '2015-10-30', 'Active'],
            ['273.102.126', 'Pengoperasian GTA AAS 120 Varian', 'IK', '04', '2015-10-30', 'Active'],
            ['273.102.127', 'Pengoperasian ICP OES 725 ES Varian', 'IK', '04', '2015-10-30', 'Active'],
            ['273.102.128', 'Pengoperasian XRF', 'IK', '05', '2019-06-17', 'Active'],
            ['273.102.129', 'Pengoperasian Mesin Fusebead', 'IK', '04', '2015-10-30', 'Active'],
            ['273.102.130', 'Pengoperasian Elmasonic', 'IK', '04', '2015-10-30', 'Active'],
            ['273.102.131', 'Pengoperasian UV-VIS spektrometer Hitachi U 2900', 'IK', '04', '2015-10-30', 'Active'],
            ['273.102.132', 'Pengoperasian Furnace', 'IK', '04', '2015-10-30', 'Active'],
            ['273.102.133', 'Pengoperasian Surveymeter Victoren 190', 'IK', '04', '2015-10-30', 'Active'],
            ['273.102.134', 'Perawatan Rutin Fuse Bead', 'IK', '04', '2015-10-30', 'Active'],
            ['273.102.135', 'Maintenance Mold', 'IK', '04', '2015-10-30', 'Active'],
            ['273.102.136', 'Pemantauan Kondisi Lingkungan Pengujian', 'IK', '04', '2026-06-22', 'Active'],
            ['273.102.137', 'Validasi Metode Analisis', 'IK', '09', '2026-06-25', 'Active'],
            ['273.102.138', 'Quality Control', 'IK', '06', '2024-01-05', 'Active'],
            ['273.102.139', 'Menentukan Estimasi Ketidakpastian Pengukuran', 'IK', '06', '2026-06-23', 'Active'],
            ['273.102.140', 'Penanganan Penyimpanan dan Tumpahan Bahan Kimia', 'IK', '02', '2015-10-30', 'Active'],
            ['273.102.141', 'Verifikasi Program Komputer Internal', 'IK', '02', '2015-10-30', 'Active'],
            ['273.102.142', 'Pembuatan Kurva Kalibrasi Rutin', 'IK', '02', '2016-06-08', 'Active'],
            ['273.102.143', 'Uji Antar Analis', 'IK', '01', '2025-10-30', 'Active'],
            ['273.102.144', 'Analisis Kadar LOI (Loss On Ignition)', 'IK', '02', '2025-10-10', 'Active'],
            ['273.102.145', 'Perawatan AAS (Atomic Absorption Spectrophotometer)', 'IK', '01', '2026-06-29', 'Active'],
            ['273.102.146', 'Dekontaminasi Peralatan Gelas', 'IK', '00', '2016-06-16', 'Active'],
            ['273.102.147', 'Pengukuran Konduktivitas dan Resistivitas', 'IK', '00', '2016-06-16', 'Active'],
            ['273.102.148', 'Analisis Base Metals Dalam Sampel Batuan Dengan Metode Aqua Regia-AAS (AAS 2)', 'IK', '00', '2016-06-16', 'Active'],
            ['273.102.149', 'Pengoperasian ED-XRF S2 PUMA Bruker', 'IK', '00', '2017-09-26', 'Active'],
            ['273.102.150', 'Uji Profisiensi', 'IK', '00', '2021-12-13', 'Active'],
            ['273.102.151', 'Analisis LOI Dengan Metode Thermogravimetri Analyzer - TGA (TGA801)', 'IK', '00', '2022-03-29', 'Active'],
            ['273.102.152', 'Analisis Kadar Sulfur dan Karbon Dengan Metode Combustion - SC (SC832)', 'IK', '00', '2022-03-29', 'Active'],
            ['273.102.153', 'Pengoperasian ICP OES 5800 Agilent', 'IK', '01', '2024-02-02', 'Active'],
            ['273.102.154', 'Pembuatan dan Evaluasi Control Chart', 'IK', '03', '2024-02-13', 'Active'],
            ['273.102.155', 'Pengoperasian ICP OES 5900 Agilent', 'IK', '00', '2026-03-02', 'Active'],
            ['273.102.156', 'Perawatan ICP-OES 5800 & 5900', 'IK', '00', '2026-06-24', 'Active'],
            ['273.102.157', 'Perawatan XRF AXIOS FAST 4600 & AXIOS MAX 4400 (X-ray Fluorescence)', 'IK', '00', '2026-06-24', 'Active'],
            ['273.103.101', 'Pengendalian Rekaman', 'IK', '06', '2025-11-21', 'Active'],
            ['273.103.102', 'Test Report', 'IK', '07', '2019-11-15', 'Active'],
            ['273.103.103', 'Pembuatan Dokumen', 'IK', '00', '2018-01-08', 'Active'],
            ['273.103.104', 'Pengesahan Dokumen', 'IK', '00', '2018-02-15', 'Active'],
            ['273.104.101', 'Audit Internal', 'IK', '07', '2026-06-17', 'Active'],
            ['273.105.101', 'Rapat Tinjauan Manajemen', 'IK', '05', '2025-09-24', 'Active'],
            ['273.105.102', 'Pengendalian Analisis Yang Tidak Sesuai', 'IK', '04', '2015-10-30', 'Active'],
            ['273.105.103', 'Pengukuran Kepuasan Pelanggan', 'IK', '04', '2015-10-30', 'Active'],
            ['273.105.104', 'Penanganan Pengaduan', 'IK', '05', '2015-12-04', 'Active'],
            ['273.105.105', 'Kaji ulang permintaan tender, kontrak, subkontrak pengujian - kalibrasi', 'IK', '04', '2015-10-30', 'Active'],
            ['273.105.106', 'Pengadaan jasa dan perbekalan', 'IK', '06', '2018-01-08', 'Active'],
            ['273.105.107', 'Analisa kebutuhan pelatihan', 'IK', '04', '2015-10-30', 'Active'],
            ['273.105.108', 'Uji T', 'IK', '06', '2015-10-30', 'Active'],
            ['273.105.109', 'Uji Banding', 'IK', '04', '2021-12-07', 'Active'],
            ['273.105.110', 'Perbaikan, pencegahan dan peningkatan', 'IK', '03', '2018-10-08', 'Active'],
            ['273.105.111', 'Pelayanan kepada pelanggan', 'IK', '02', '2015-10-30', 'Active'],
            ['273.105.112', 'UJI HOMOGENITAS', 'IK', '02', '2015-10-30', 'Active'],
            ['273.105.113', 'UJI STABILITAS', 'IK', '02', '2015-10-30', 'Active'],
            ['273.105.114', 'Monitoring Penyimpanan Bahan Habis Pakai', 'IK', '02', '2022-03-21', 'Active'],
            ['273.105.115', 'Kaji ulang unjuk kerja personil laboratorium', 'IK', '01', '2015-10-30', 'Active'],
            ['273.105.116', 'Monitoring Inventaris Laboratorium', 'IK', '01', '2015-10-30', 'Active'],
            ['273.105.117', 'Analisis Sampel Melalui Laboratorium Subkontraktor', 'IK', '00', '2016-06-09', 'Active'],
            ['273.106.101', 'Pembuatan dan Penerbitan Dokumen', 'IK', '08', '2025-01-03', 'Active'],
            ['273.106.102', 'Pemeliharaan Dokumen', 'IK', '01', '2018-10-08', 'Active'],
            ['273.106.103', 'Pengubahan Dokumen', 'IK', '01', '2018-10-08', 'Active'],
            ['273.106.104', 'Pemusnahan Dokumen', 'IK', '02', '2025-01-03', 'Active'],
            ['273.106.105', 'Kaji Ulang Dokumen', 'IK', '02', '2018-10-08', 'Active'],
            ['273.106.106', 'Pengoperasian Aplikasi GLSR dalam Pengendalian Dokumen Mutu Berbasis ISO IEC 17025', 'IK', '00', '2026-05-12', 'Active'],
            ['273.107.101', 'Pengecekan Antara Peralatan', 'IK', '00', '2022-03-29', 'Active'],
            ['273.107.102', 'Penanganan Batu Timbang', 'IK', '00', '2022-03-29', 'Active'],
            ['273.107.103', 'Penanganan Alat Gelas', 'IK', '00', '2022-03-29', 'Active'],
            ['273.101.201', 'Form Penerimaan Sampel', 'F', '02', '2016-12-30', 'Active'],
            ['273.101.202', 'Pernyataan Menjaga Kerahasiaan Informasi dan Hak Kepemilikan Pelanggan dan Laboratorium', 'F', '03', '2019-01-21', 'Active'],
            ['273.101.203', 'Form Checklist Penerimaan Sampel', 'F', '07', '2026-02-17', 'Active'],
            ['273.101.204', 'Form Konfirmasi Ketidak Sesuaian Pengujian, Order, Kontrak', 'F', '00', '2013-01-10', 'Active'],
            ['273.101.205', 'Form Monitoring Preparasi XRD', 'F', '01', '2016-06-06', 'Active'],
            ['273.101.206', 'Form Monitoring Pemakaian Alat', 'F', '03', '2026-06-18', 'Active'],
            ['273.101.207', 'Form Screen Manual Preparasi', 'F', '03', '2016-06-03', 'Active'],
            ['273.101.208', 'Form Quality Control Preparasi', 'F', '01', '2016-06-06', 'Active'],
            ['273.101.209', 'Form Check List Blank Bowl', 'F', '02', '2016-06-06', 'Active'],
            ['273.101.210', 'Form Serah Terima Duplikat', 'F', '01', '2016-06-06', 'Active'],
            ['273.101.211', 'Form Permintaan Pengadaan Barang Di Lapangan', 'F', '01', '2013-11-01', 'Active'],
            ['273.101.212', 'Form Monitoring Preparasi Petmin', 'F', '01', '2016-06-06', 'Active'],
            ['273.101.213', 'Form Monitoring Analisa Selesai Terhadap Sampel Masuk', 'F', '01', '2016-06-06', 'Active'],
            ['273.101.214', 'Form Rekapitulasi Preparasi dan Analisa Emas Nikel Bauksit Mingguan', 'F', '01', '2016-06-06', 'Active'],
            ['273.101.215', 'Form Rekapitulasi Preparasi dan Analisa Emas Nikel Bauksit Triwulan', 'F', '01', '2016-06-06', 'Active'],
            ['273.101.216', 'Form Laporan Kontrol Pengovenan', 'F', '00', '2013-01-10', 'Active'],
            ['273.101.217', 'Form Isian Kegiatan Maintanance Harian Peralatan Laboratorium', 'F', '01', '2013-11-01', 'Active'],
            ['273.101.218', 'Form Monitoring Preparasi Lapangan', 'F', '03', '2024-02-13', 'Active'],
            ['273.101.219', 'Form Daftar Duplikat Sampel per Palet', 'F', '02', '2021-11-18', 'Active'],
            ['273.101.220', 'Form Pemakaian Crucible Clay', 'F', '00', '2014-05-08', 'Active'],
            ['273.101.221', 'Form Monitoring Pembuangan Limbah', 'F', '00', '2014-08-12', 'Active'],
            ['273.101.222', 'Formulir Monitoring Standar Acuan', 'F', '00', '2014-09-28', 'Active'],
            ['273.101.223', 'Form Monitoring KEMAJUAN PREPARASI', 'F', '00', '2016-07-29', 'Active'],
            ['273.101.224', 'Form Monitoring KEMAJUAN FIRE ASSAY', 'F', '00', '2016-07-29', 'Active'],
            ['273.101.225', 'Form Jadwal Maintenance Alat', 'F', '01', '2014-09-29', 'Active'],
            ['273.101.226', 'Form Pelaksanaan dan Pemeriksaan Harian Mesin Press', 'F', '00', '2024-01-13', 'Active'],
            ['273.101.227', 'Form Hasil Pengerjaan Fire Assay', 'F', '01', '2025-01-03', 'Active'],
            ['273.101.228', 'Form Sample Receipt Advice', 'F', '00', '2025-10-22', 'Active'],
            ['273.102.201', 'Form Monitoring Analisis', 'F', '01', '2013-05-17', 'Active'],
            ['273.102.202', 'Form Validasi Metode Analisis', 'F', '03', '2025-11-20', 'Active'],
            ['273.102.203', 'Form Monitoring QC', 'F', '00', '2013-01-09', 'Active'],
            ['273.102.204', 'Form Ketidakpastian Pengukuran', 'F', '02', '2022-03-29', 'Active'],
            ['273.102.205', 'Form Data QC Duplikat (PV&JC) atau Replikat (R&RR)', 'F', '01', '2014-10-01', 'Active'],
            ['273.102.206', 'Form Jadwal Verifikasi Program Komputer Internal', 'F', '00', '2014-01-05', 'Active'],
            ['273.102.207', 'Form Kurva Kalibrasi', 'F', '01', '2022-03-28', 'Active'],
            ['273.102.208', 'Uji Hasil Antar Personil', 'F', '01', '2021-12-08', 'Active'],
            ['273.102.209', 'Form Pengukuran Paparan Radiasi', 'F', '00', '2015-12-14', 'Active'],
            ['273.102.210', 'Form Pemantauan Kualitas Aquadest atau Aquabidest', 'F', '00', '2016-06-16', 'Active'],
            ['273.102.211', 'Form Verifikasi Kinerja Alat Internal', 'F', '00', '2017-11-17', 'Active'],
            ['273.102.212', 'Batas Kontrol Nilai Pengukuran CRM', 'F', '00', '2024-01-05', 'Active'],
            ['273.102.213', 'Form Extrafooding', 'F', '00', '2025-09-22', 'Active'],
            ['273.103.201', 'Monitoring Sasaran Mutu Laboratorium', 'F', '02', '2015-10-30', 'Active'],
            ['273.103.202', 'Form Test Report', 'F', '04', '2024-09-04', 'Active'],
            ['273.103.203', 'Form Daftar Induk Rekaman Mutu Laboratoy Bureau', 'F', '01', '2014-08-13', 'Active'],
            ['273.103.204', 'Form Rekap Penerimaan Sampel', 'F', '01', '2016-06-06', 'Active'],
            ['273.103.205', 'Form Kartu Peralatan', 'F', '01', '2014-10-28', 'Active'],
            ['273.103.206', 'Form List Revisions Analysis Report', 'F', '02', '2022-03-29', 'Active'],
            ['273.103.207', 'Form List Of Report', 'F', '01', '2016-03-14', 'Active'],
            ['273.103.208', 'Form Kartu Stok Standar Acuan (CRM)', 'F', '01', '2026-06-24', 'Active'],
            ['273.104.201', 'Form Program Audit Internal Tahunan', 'F', '00', '2013-01-09', 'Active'],
            ['273.104.202', 'Lembar Temuan Ketidaksesuaian Audit Internal', 'F', '02', '2016-11-29', 'Active'],
            ['273.104.203', 'Form Penerapan SM SNI ISO IEC 17025', 'F', '00', '2016-12-05', 'Active'],
            ['273.104.204', 'Formulir verifikasi tindakan perbaikan audit internal', 'F', '02', '2019-06-17', 'Active'],
            ['273.104.205', 'Daftar periksa audit internal', 'F', '02', '2026-06-18', 'Active'],
            ['273.104.206', 'Evaluasi Kinerja Auditor', 'F', '00', '2025-10-06', 'Active'],
            ['273.104.207', 'Evaluasi Ketidakberpihakan Laboratorium Geomin', 'F', '00', '2025-10-06', 'Active'],
            ['273.105.201', 'Formulir Hasil Kaji Ulang Manajemen', 'F', '02', '2025-09-24', 'Active'],
            ['273.105.202', 'Identifikasi Tindakan Ketidak Sesuaian Dan Tindakan Perbaikan', 'F', '00', '2013-01-09', 'Active'],
            ['273.105.203', 'Survey Kepuasan Pelanggan Atas Jasa Pelayanan Laboratorium', 'F', '03', '2016-10-12', 'Active'],
            ['273.105.204', 'Formulir Perencanaan Pelatihan Personil Laboratorium', 'F', '00', '2013-01-09', 'Active'],
            ['273.105.205', 'Formulir Evaluasi Pelatihan Personil Laboratorium', 'F', '01', '2014-01-02', 'Active'],
            ['273.105.206', 'Form Safety Talk', 'F', '00', '2013-01-10', 'Active'],
            ['273.105.207', 'Form Safety Meeting', 'F', '02', '2015-10-30', 'Active'],
            ['273.105.208', 'Uji Statistik', 'F', '02', '2016-06-06', 'Active'],
            ['273.105.209', 'Form Monitoring Inventaris Laboratorium', 'F', '02', '2023-05-26', 'Active'],
            ['273.105.210', 'Formulir Pemantauan Kondisi Lingkungan Pengujian', 'F', '06', '2026-06-22', 'Active'],
            ['273.105.211', 'Daftar Evaluasi Dan Pemilihan Pemasok', 'F', '00', '2013-01-09', 'Active'],
            ['273.105.212', 'Program Pengendalian Mutu', 'F', '03', '2016-11-29', 'Active'],
            ['273.105.213', 'Daftar Laboratorium Subkontraktor yang Kompeten', 'F', '00', '2013-01-09', 'Active'],
            ['273.105.214', 'Daftar Riwayat Hidup', 'F', '00', '2013-09-02', 'Active'],
            ['273.105.215', 'Form Job Description', 'F', '01', '2014-09-23', 'Active'],
            ['273.105.216', 'Form Pengendalian Analisis yang Tidak Sesuai', 'F', '01', '2016-11-29', 'Active'],
            ['273.105.217', 'Form Risalah Kajian Manajemen', 'F', '00', '2013-09-11', 'Active'],
            ['273.105.218', 'Form Daftar Rekaman pekerjaan yang tidak sesuai dan tindakan perbaikan', 'F', '00', '2013-11-01', 'Active'],
            ['273.105.219', 'Formulir Evaluasi Kinerja Personil Laboratorium (Tenaga Kontrak)', 'F', '00', null, 'Obsolete'],
            ['273.105.220', 'Formulir Pelaksanaan Pelatihan Personil Laboratorium (Tenaga Kerja Baru)', 'F', '01', null, 'Obsolete'],
            ['273.105.221', 'Formulir Monitoring penerimaan dan penyimpanan bahan habis pakai laboratorium mingguan', 'F', '00', '2014-08-12', 'Active'],
            ['273.105.222', 'Form Evaluasi Hasil Survey Kepuasan Pelanggan', 'F', '02', '2016-10-12', 'Active'],
            ['273.105.223', 'Uji Homogenitas', 'F', '01', '2016-06-06', 'Active'],
            ['273.105.224', 'From Uji Stabilitas', 'F', '01', '2016-06-06', 'Active'],
            ['273.105.225', 'Form Pengaduan Pelanggan', 'F', '00', '2015-12-04', 'Active'],
            ['273.105.226', 'Form Persetujuan Pelanggan untuk Analisis Sampel di Lab Subkontraktor', 'F', '00', '2016-06-09', 'Active'],
            ['273.105.227', 'Form Evaluasi Laboratorium Subkontraktor', 'F', '00', '2016-06-09', 'Active'],
            ['273.105.228', 'Form Evaluasi Pemasok', 'F', '01', '2026-06-18', 'Active'],
            ['273.105.229', 'Identifikasi Pengendalian resiko Proses Analisis', 'F', '00', '2018-10-08', 'Active'],
            ['273.105.230', 'Form Permintaan Perbaikan Alat', 'F', '01', '2023-06-27', 'Active'],
            ['273.105.231', 'Form Monitoring Perbaikan Alat', 'F', '00', '2021-06-09', 'Active'],
            ['273.106.201', 'Daftar Distribusi Dokumen', 'F', '00', '2013-01-09', 'Active'],
            ['273.106.202', 'Daftar Induk Dokumen', 'F', '04', '2026-09-07', 'Active'],
            ['273.106.203', 'Daftar Induk Dokumen Eksternal', 'F', '00', '2013-11-01', 'Active'],
            ['273.106.204', 'Usulan permintaan atau perubahan dokumen', 'F', '00', '2013-11-01', 'Active'],
            ['273.106.205', 'Evaluasi Kaji Ulang Dokumen', 'F', '01', '2025-10-22', 'Active'],
            ['273.106.206', 'Form Serah Terima Dokumen', 'F', '00', '2020-06-13', 'Active'],
            ['273.106.207', 'Berita Acara Pemusnahan Dokumen', 'F', '00', '2026-06-22', 'Active'],
            ['273.106.208', 'Berita Acara Pemusnahan Rekaman', 'F', '00', '2026-06-22', 'Active'],
            ['273.107.201', 'Form Evaluasi Hasil Kalibrasi', 'F', '01', '2022-03-29', 'Active'],
            ['273.107.202', 'Form Daftar Status Kalibrasi', 'F', '01', '2014-10-01', 'Active'],
            ['273.107.203', 'Form Label Status Kalibrasi', 'F', '00', '2022-03-29', 'Active'],
            ['273.107.204', 'Form Data Verifikasi Timbangan', 'F', '00', '2022-03-29', 'Active'],
            ['273.107.205', 'Form Verifikasi Suhu (Oven atau Furnace atau Hotplate)', 'F', '02', '2016-01-04', 'Active'],
            ['273.107.206', 'Form Verifikasi volume', 'F', '00', '2022-03-29', 'Active'],
            ['273.107.207', 'Form Kaji Ulang Permintaan', 'F', '00', '2024-01-12', 'Active'],
            ['273.107.208', 'Form Evaluasi Pemasok', 'F', '00', '2026-06-18', 'Active'],
            ['273.108.201', 'Pelatihan dan pendampingan personil baru', 'F', '01', '2025-11-21', 'Active'],
            ['273.108.202', 'Evaluasi keefektifan pelatihan', 'F', '01', '2025-11-21', 'Active'],
            ['273.108.203', 'Form penilaian prestasi individu karyawan outsourcing TAD', 'F', '00', '2022-03-29', 'Active'],
        ];
    }
}
