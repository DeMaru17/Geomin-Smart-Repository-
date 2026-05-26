<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentRevision;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DocumentSeeder extends Seeder
{
    /**
     * Seed documents dan revisi berdasarkan data standar ISO lab Geomin.
     *
     * Category mapping: 1=PK (Prosedur Kerja), 2=IK (Instruksi Kerja), 3=F (Form)
     * Department mapping berdasarkan kode: 101=PREPARASI, 102=ANALISIS, dst.
     */
    public function run(): void
    {
        // Resolve category IDs by code
        $categories = DocumentCategory::pluck('id', 'code')->toArray();
        $pkId = $categories['PK'] ?? null;
        $ikId = $categories['IK'] ?? null;
        $fId  = $categories['F'] ?? null;

        // Resolve department IDs by code
        $departments = Department::pluck('id', 'code')->toArray();

        // Category mapping: 1=PK, 2=IK, 3=Form
        $categoryMap = [1 => $pkId, 2 => $ikId, 3 => $fId];

        // Department mapping by document_number prefix
        $deptCodeMap = [
            '273.101' => $departments['101'] ?? null,
            '273.102' => $departments['102'] ?? null,
            '273.103' => $departments['103'] ?? null,
            '273.104' => $departments['104'] ?? null,
            '273.105' => $departments['105'] ?? null,
            '273.106' => $departments['106'] ?? null,
            '273.107' => $departments['107'] ?? null,
            '273.108' => $departments['108'] ?? null,
        ];

        $uploaderId = 1; // Admin user

        $documentsRaw = $this->getDocumentsData();

        foreach ($documentsRaw as $doc) {
            [$docNumber, $title, $catKey, $revNumber] = $doc;

            // Determine department from document_number prefix
            $deptPrefix = substr($docNumber, 0, 7); // e.g. '273.101'
            $departmentId = $deptCodeMap[$deptPrefix] ?? null;

            $categoryId = $categoryMap[$catKey] ?? null;

            // Determine publication year from revision number
            $revInt = (int) $revNumber;
            $pubYear = 2020 + (int) floor($revInt / 3);

            $document = Document::updateOrCreate(
                ['document_number' => $docNumber],
                [
                    'title' => $title,
                    'category_id' => $categoryId,
                    'department_id' => $departmentId,
                    'is_external' => false,
                    'publication_year' => min($pubYear, 2025),
                    'retention_period_months' => 60,
                    'status' => 'Active',
                ],
            );

            // Create Published revision if none exists
            $existingRevision = $document->revisions()
                ->where('revision_number', $revNumber)
                ->first();

            if (! $existingRevision) {
                // Calculate a realistic revision date
                $monthsAgo = rand(1, 18);
                $revisionDate = Carbon::today()->subMonths($monthsAgo)->subDays(rand(0, 28));

                DocumentRevision::create([
                    'document_id' => $document->id,
                    'revision_number' => $revNumber,
                    'file_path' => 'documents/' . Str::slug($docNumber) . '_rev' . $revNumber . '.pdf',
                    'status' => 'Published',
                    'revision_date' => $revisionDate,
                    'change_summary' => 'Revisi ' . $revNumber . ' - ' . $title,
                    'qr_token' => Str::uuid()->toString(),
                    'uploader_id' => $uploaderId,
                ]);
            }
        }

        $this->command->info('Seeded ' . count($documentsRaw) . ' documents with Published revisions.');
    }

    /**
     * Data dokumen lab ISO.
     * Format: [document_number, title, category_key (1=PK, 2=IK, 3=F), revision_number]
     */
    private function getDocumentsData(): array
    {
        return [
            // === KELOMPOK PREPARASI (273.101) ===
            ['273.101', 'Preparasi', 1, '15'],
            ['273.101.101', 'Penerimaan dan pengecekan conto', 2, '08'],
            ['273.101.102', 'Preparasi Conto emas soil, rock dan core', 2, '09'],
            ['273.101.103', 'Preparasi Total Sampel Batuan', 2, '08'],
            ['273.101.104', 'Preparasi Sampel Endapan Sungai (Stream Sediment)', 2, '06'],
            ['273.101.105', 'Preparasi Sampel Batuan Nikel', 2, '07'],
            ['273.101.106', 'Preparasi Sampel Batuan Bauksit', 2, '06'],
            ['273.101.107', 'Pembuatan QC Preparasi', 2, '07'],
            ['273.101.108', 'Penanganan dan Penyimpanan Standar Acuan', 2, '06'],
            ['273.101.109', 'Pengarsipan Sampel', 2, '06'],
            ['273.101.110', 'Pengoperasian Oven', 2, '05'],
            ['273.101.111', 'Pengoperasian oven kecil', 2, '05'],
            ['273.101.112', 'Pengoperasian jaw crusher', 2, '05'],
            ['273.101.113', 'Pengoperasian pulverizer', 2, '06'],
            ['273.101.114', 'Pengoperasian Dust Collector', 2, '05'],
            ['273.101.115', 'Pengoperasian kompresor', 2, '05'],
            ['273.101.116', 'Pengoperasian alat-alat fire assay', 2, '05'],
            ['273.101.117', 'Pengoperasian mesin press', 2, '04'],
            ['273.101.118', 'Pengoperasian genset perkins', 2, '05'],
            ['273.101.119', 'Perawatan rutin oven', 2, '05'],
            ['273.101.120', 'Perawatan rutin jaw crusher', 2, '05'],
            ['273.101.121', 'Perawatan rutin pulverizer', 2, '05'],
            ['273.101.122', 'Perawatan rutin dust collector', 2, '05'],
            ['273.101.123', 'Perawatan rutin mesin kompressor', 2, '05'],
            ['273.101.124', 'Perawatan rutin alat-alat fire assay', 2, '05'],
            ['273.101.125', 'Perawatan Rutin mesin press', 2, '05'],
            ['273.101.126', 'Perawatan rutin mesin genset', 2, '05'],
            ['273.101.127', 'Preparasi Sample Sayatan Tipis, Sayatan Poles & Doble Poles', 2, '05'],
            ['273.101.128', 'Perawatan Alat Preparasi Petrografi', 2, '05'],
            ['273.101.129', 'Penanganan Limbah (Cair dan Padat)', 2, '02'],
            ['273.101.130', 'Pembuatan Penyimpanan atau Shelter Limbah Sementara', 2, '02'],
            ['273.101.131', 'Pembuatan Alas Furnace Dengan Menggunakan Semen Tahan Api', 2, '02'],
            ['273.101.132', 'Pembuatan Ducting dari Jaw Crusher dan Meja Split + Mixing di Pomalaa', 2, '02'],
            ['273.101.133', 'Pembuatan Heater Furnace Dengan Menggunakan Kawat Nikelin dan Semen Tahan Api', 2, '01'],
            ['273.101.134', 'Press Manual Specac', 2, '01'],
            ['273.101.135', 'Preparasi Sampel Pengapalan', 2, '01'],
            ['273.101.136', 'Preparasi Sampel Pra Produksi', 2, '01'],
            ['273.101.137', 'Preparasi Sampel Produksi', 2, '01'],
            ['273.101.138', 'Pengujian Moisture Content', 2, '01'],
            ['273.101.139', 'Pengujian Moisture Content Sampel Eksplorasi', 2, '01'],
            ['273.101.140', 'Pengiriman Sampel', 2, '01'],
            ['273.101.141', 'Pengoperasian v-mixer', 2, '00'],
            ['273.101.201', 'Form Penerimaan Sampel', 3, '02'],
            ['273.101.202', 'Pernyataan Menjaga Kerahasiaan Informasi dan Hak Kepemilikan Pelanggan', 3, '03'],
            ['273.101.203', 'Form Checklist Penerimaan Sampel', 3, '05'],
            ['273.101.204', 'Form Konfirmasi Ketidaksesuaian Pengujian, Order, Kontrak', 3, '00'],
            ['273.101.205', 'Form Monitoring Preparasi XRD', 3, '01'],
            ['273.101.206', 'Form Monitoring Pemakaian Alat & Sparepart Mesin', 3, '01'],
            ['273.101.207', 'Form Screen Manual Preparasi', 3, '03'],
            ['273.101.208', 'Form Quality Control Preparasi', 3, '01'],
            ['273.101.209', 'Form Check List Blank Bowl', 3, '02'],
            ['273.101.210', 'Form Serah Terima Duplikat', 3, '01'],
            ['273.101.211', 'Form Permintaan Pengadaan Barang Di Lapangan', 3, '01'],
            ['273.101.212', 'Form Monitoring Preparasi Petmin', 3, '01'],
            ['273.101.213', 'Form Monitoring Analisa Selesai Terhadap Sampel Masuk', 3, '01'],
            ['273.101.214', 'Form Rekapitulasi Preparasi dan Analisa Emas Nikel Bauksit Mingguan', 3, '01'],
            ['273.101.215', 'Form Rekapitulasi Preparasi dan Analisa Emas Nikel Bauksit Triwulan', 3, '01'],
            ['273.101.216', 'Form Laporan Kontrol Pengovenan', 3, '00'],
            ['273.101.217', 'Form Isian Kegiatan Maintenance Harian Peralatan Laboratorium', 3, '01'],
            ['273.101.218', 'Form Monitoring Preparasi Lapangan', 3, '02'],
            ['273.101.219', 'Form Daftar Duplikat Sampel per Palet', 3, '02'],
            ['273.101.220', 'Form Pemakaian Crucible Clay', 3, '00'],
            ['273.101.221', 'Form Monitoring Pembuangan Limbah', 3, '00'],
            ['273.101.222', 'Formulir Monitoring Standar Acuan', 3, '00'],
            ['273.101.223', 'Form Monitoring Kemajuan Preparasi', 3, '00'],
            ['273.101.224', 'Form Monitoring Kemajuan Fire Assay', 3, '00'],
            ['273.101.225', 'Form Jadwal Maintenance Alat', 3, '00'],

            // === KELOMPOK ANALISIS (273.102) ===
            ['273.102', 'Analisis', 1, '16'],
            ['273.102.101', 'Analisis conto base metal metode perchloric (AAS 1)', 2, '04'],
            ['273.102.102', 'Analisis conto base metal metode multi acid (AAS 3)', 2, '08'],
            ['273.102.103', 'Analisis emas metode aqua regia ppm (AAS 5)', 2, '04'],
            ['273.102.104', 'Analisis emas metode aqua regia ppb (AAS 6)', 2, '04'],
            ['273.102.105', 'Analisis emas ppm metode fire assay - AAS (FA-01)', 2, '07'],
            ['273.102.106', 'Analisis emas ppb metode fire assay - AAS (FA-02)', 2, '04'],
            ['273.102.107', 'Analisis emas ppm metode fire assay - gravimetri (FA-03)', 2, '04'],
            ['273.102.108', 'Analisis emas metode fire assay - AAS (FA-04)', 2, '04'],
            ['273.102.109', 'Analisis Geokimia Metode Aqua Regia dengan ICP OES (ICP 01)', 2, '04'],
            ['273.102.110', 'Analisis Geokimia Metode Multi Acid dengan ICP OES (ICP 02)', 2, '06'],
            ['273.102.111', 'Analisis nikel metode Press Powder - XRF (XRF 01)', 2, '07'],
            ['273.102.112', 'Analisis nikel metode wax - XRF (XRF 03)', 2, '04'],
            ['273.102.113', 'Analisis bauksit metode fusion (XRF 02)', 2, '07'],
            ['273.102.114', 'Analisis TSiO2 conto bauksit metode gravimetri (GM 01)', 2, '04'],
            ['273.102.115', 'Analisis Fe2O3 conto bauksit metode volumetri (VM01)', 2, '04'],
            ['273.102.116', 'Analisis TiO2 conto bauksit metode spektrofometer (UVM01)', 2, '04'],
            ['273.102.117', 'Analisis RSiO2 conto bauksit metode gravimetri (GM 01)', 2, '06'],
            ['273.102.118', 'Analisis Al2O3 conto bauksit metode WET', 2, '04'],
            ['273.102.119', 'Pencucian keramik, mold dan crucible platina', 2, '04'],
            ['273.102.120', 'Pengoperasian timbangan analitik (satu desimal)', 2, '04'],
            ['273.102.121', 'Pengoperasian timbangan analitik (dua desimal)', 2, '04'],
            ['273.102.122', 'Pengoperasian timbangan analitik (empat desimal)', 2, '05'],
            ['273.102.123', 'Pengoperasian ultra micro balance', 2, '04'],
            ['273.102.124', 'Pengoperasian hotplate dan ruang asam', 2, '04'],
            ['273.102.125', 'Pengoperasian AAS 240 FS dan AAS 280 FS Varian', 2, '04'],
            ['273.102.126', 'Pengoperasian GTA AAS 120 Varian', 2, '04'],
            ['273.102.127', 'Pengoperasian ICP OES 725 ES Varian', 2, '04'],
            ['273.102.128', 'Pengoperasian XRF PW 2400', 2, '05'],
            ['273.102.129', 'Persiapan analisis bauksit dengan fuse bead', 2, '04'],
            ['273.102.130', 'Pengoperasian Elmasonic', 2, '04'],
            ['273.102.131', 'Pengoperasian UV-VIS spektrometer Hitachi U 2900', 2, '04'],
            ['273.102.132', 'Pengoperasian Furnace', 2, '04'],
            ['273.102.133', 'Pengoperasian Surveymeter Victoren 190', 2, '04'],
            ['273.102.134', 'Perawatan Rutin Fuse Bead', 2, '04'],
            ['273.102.135', 'Maintenance Mold', 2, '04'],
            ['273.102.136', 'Pemantauan Kondisi Lingkungan Pengujian', 2, '02'],
            ['273.102.137', 'Validasi Metode Analisis', 2, '06'],
            ['273.102.138', 'Quality Control', 2, '05'],
            ['273.102.139', 'Menentukan Estimasi Ketidakpastian Pengukuran', 2, '05'],
            ['273.102.140', 'Penanganan Penyimpanan dan Tumpahan Bahan Kimia', 2, '02'],
            ['273.102.141', 'Verifikasi Program Komputer Internal', 2, '02'],
            ['273.102.142', 'Pembuatan Kurva Kalibrasi Rutin', 2, '02'],
            ['273.102.143', 'Uji Antar Analis', 2, '00'],
            ['273.102.144', 'Analisis Kadar LOI (Loss On Ignition)', 2, '01'],
            ['273.102.145', 'Perawatan AAS (Atomic Absorption Spectrophotometer)', 2, '00'],
            ['273.102.146', 'Dekontaminasi Peralatan Gelas', 2, '00'],
            ['273.102.147', 'Pengukuran Konduktivitas dan Resistivitas', 2, '00'],
            ['273.102.148', 'Analisis Base Metals Dalam Sampel Batuan Metode Aqua Regia-AAS (AAS 2)', 2, '00'],
            ['273.102.149', 'Pengoperasian ED-XRF S2 PUMA Bruker', 2, '00'],
            ['273.102.150', 'Uji Profisiensi', 2, '00'],
            ['273.102.151', 'Analisis LOI Dengan Metode Thermogravimetri Analyzer - TGA (TGA801)', 2, '00'],
            ['273.102.152', 'Analisis Kadar Sulfur dan Karbon Dengan Metode Combustion - SC (SC832)', 2, '00'],
            ['273.102.201', 'Form Monitoring Analisis', 3, '01'],
            ['273.102.202', 'Form Validasi Metode Analisis', 3, '02'],
            ['273.102.203', 'Form Monitoring QC', 3, '00'],
            ['273.102.204', 'Form Ketidakpastian Pengukuran', 3, '02'],
            ['273.102.205', 'Form Data QC Duplikat (PV&JC) atau Replikat (R&RR)', 3, '01'],
            ['273.102.206', 'Form Jadwal Verifikasi Program Komputer Internal', 3, '00'],
            ['273.102.207', 'Form Kurva Kalibrasi', 3, '00'],
            ['273.102.208', 'Uji Hasil Antar Personil', 3, '01'],
            ['273.102.209', 'Form Pengukuran Paparan Radiasi', 3, '00'],
            ['273.102.210', 'Form Pemantauan Kualitas Aquadest atau Aquabidest', 3, '00'],
            ['273.102.211', 'Form Verifikasi Kinerja Alat Internal', 3, '00'],

            // === KELOMPOK PENGENDALIAN REKAMAN (273.103) ===
            ['273.103', 'Pengendalian Rekaman', 1, '09'],
            ['273.103.101', 'Pengendalian Rekaman', 2, '05'],
            ['273.103.102', 'Test Report', 2, '07'],
            ['273.103.103', 'Pembuatan Dokumen', 2, '00'],
            ['273.103.104', 'Pengesahan Dokumen', 2, '00'],
            ['273.103.201', 'Monitoring Sasaran Mutu Laboratorium', 3, '02'],
            ['273.103.202', 'Form Test Report', 3, '04'],
            ['273.103.203', 'Form Daftar Induk Rekaman Mutu Laboratory Bureau', 3, '01'],
            ['273.103.204', 'Form Rekap Penerimaan Sampel', 3, '01'],
            ['273.103.205', 'Form Kartu Peralatan', 3, '01'],
            ['273.103.206', 'Form List Revisions Analysis Report', 3, '02'],
            ['273.103.207', 'Form List Of Report', 3, '01'],
            ['273.103.208', 'Form Kartu Stok Standar Acuan (CRM)', 3, '00'],

            // === KELOMPOK AUDIT INTERNAL (273.104) ===
            ['273.104', 'Audit Internal', 1, '08'],
            ['273.104.101', 'Audit Internal', 2, '05'],
            ['273.104.201', 'Form Program Audit Internal Tahunan', 3, '00'],
            ['273.104.202', 'Lembar Temuan Ketidaksesuaian Audit Internal', 3, '02'],
            ['273.104.203', 'Form Penerapan SM SNI ISO IEC 17025', 3, '00'],
            ['273.104.204', 'Formulir verifikasi tindakan perbaikan audit internal', 3, '02'],
            ['273.104.205', 'Verifikasi kesesuaian QM SNI ISO-IEC 17025', 3, '01'],

            // === KELOMPOK KAJI ULANG MANAJEMEN (273.105) ===
            ['273.105', 'Kaji Ulang Manajemen', 1, '14'],
            ['273.105.101', 'Rapat Tinjauan Manajemen', 2, '04'],
            ['273.105.102', 'Pengendalian Analisis Yang Tidak Sesuai', 2, '04'],
            ['273.105.103', 'Pengukuran Kepuasan Pelanggan', 2, '04'],
            ['273.105.104', 'Penanganan Pengaduan', 2, '05'],
            ['273.105.105', 'Kaji ulang permintaan tender, kontrak, subkontrak pengujian - kalibrasi', 2, '04'],
            ['273.105.106', 'Pengadaan jasa dan perbekalan', 2, '06'],
            ['273.105.107', 'Analisa kebutuhan pelatihan', 2, '04'],
            ['273.105.108', 'Uji T', 2, '06'],
            ['273.105.109', 'Uji Banding', 2, '04'],
            ['273.105.110', 'Perbaikan, pencegahan dan peningkatan', 2, '03'],
            ['273.105.111', 'Pelayanan kepada pelanggan', 2, '02'],
            ['273.105.112', 'Uji Homogenitas', 2, '02'],
            ['273.105.113', 'Uji Stabilitas', 2, '02'],
            ['273.105.114', 'Monitoring Penyimpanan Bahan Habis Pakai', 2, '02'],
            ['273.105.115', 'Kaji ulang unjuk kerja personil laboratorium', 2, '01'],
            ['273.105.116', 'Monitoring Inventaris Laboratorium', 2, '01'],
            ['273.105.117', 'Analisis Sampel Melalui Laboratorium Subkontraktor', 2, '00'],
            ['273.105.201', 'Formulir Hasil Kaji Ulang Manajemen', 3, '01'],
            ['273.105.202', 'Identifikasi Tindakan Ketidaksesuaian Dan Tindakan Perbaikan', 3, '00'],
            ['273.105.203', 'Survey Kepuasan Pelanggan Atas Jasa Pelayanan Laboratorium', 3, '03'],
            ['273.105.204', 'Formulir Perencanaan Pelatihan Personil Laboratorium', 3, '00'],
            ['273.105.205', 'Formulir Evaluasi Pelatihan Personil Laboratorium', 3, '01'],
            ['273.105.206', 'Form Safety Talk', 3, '00'],
            ['273.105.207', 'Form Safety Meeting', 3, '02'],
            ['273.105.208', 'Uji Statistik', 3, '02'],
            ['273.105.209', 'Form Monitoring Inventaris Laboratorium', 3, '01'],
            ['273.105.210', 'Formulir Pemantauan Kondisi Lingkungan Pengujian', 3, '04'],
            ['273.105.211', 'Daftar Evaluasi Dan Pemilihan Pemasok', 3, '00'],
            ['273.105.212', 'Program Pengendalian Mutu', 3, '03'],
            ['273.105.213', 'Daftar Laboratorium Subkontraktor yang Kompeten', 3, '00'],
            ['273.105.214', 'Daftar Riwayat Hidup', 3, '00'],
            ['273.105.215', 'Form Job Description', 3, '01'],
            ['273.105.216', 'Form Pengendalian Analisis yang Tidak Sesuai', 3, '01'],
            ['273.105.217', 'Form Risalah Kajian Manajemen', 3, '00'],
            ['273.105.218', 'Form Daftar Rekaman pekerjaan yang tidak sesuai dan tindakan perbaikan', 3, '00'],
            ['273.105.219', 'Formulir Evaluasi Kinerja Personil Laboratorium (Tenaga Kontrak)', 3, '00'],
            ['273.105.220', 'Formulir Pelaksanaan Pelatihan Personil Laboratorium (Tenaga Kerja Baru)', 3, '01'],
            ['273.105.221', 'Formulir Monitoring penerimaan dan penyimpanan bahan habis pakai laboratorium', 3, '00'],
            ['273.105.222', 'Form Evaluasi Hasil Survey Kepuasan Pelanggan', 3, '02'],
            ['273.105.223', 'Uji Homogenitas', 3, '01'],
            ['273.105.224', 'Form Uji Stabilitas', 3, '01'],
            ['273.105.225', 'Form Pengaduan Pelanggan', 3, '00'],
            ['273.105.226', 'Form Persetujuan Pelanggan untuk Analisis Sampel di Lab Subkontraktor', 3, '00'],
            ['273.105.227', 'Form Evaluasi Laboratorium Subkontraktor', 3, '00'],
            ['273.105.228', 'Evaluasi Pemasok', 3, '00'],
            ['273.105.229', 'Identifikasi Pengendalian Resiko Proses Analisis', 3, '00'],
            ['273.105.230', 'Form Permintaan Perbaikan Alat', 3, '00'],

            // === KELOMPOK PENGENDALIAN DOKUMEN (273.106) ===
            ['273.106', 'Pengendalian Dokumen', 1, '09'],
            ['273.106.101', 'Pembuatan dan Penerbitan Dokumen', 2, '07'],
            ['273.106.102', 'Pemeliharaan Dokumen', 2, '01'],
            ['273.106.103', 'Pengubahan Dokumen', 2, '01'],
            ['273.106.104', 'Pemusnahan Dokumen', 2, '01'],
            ['273.106.105', 'Kaji Ulang Dokumen', 2, '01'],
            ['273.106.201', 'Daftar Distribusi Dokumen', 3, '00'],
            ['273.106.202', 'Daftar Induk Dokumen', 3, '03'],
            ['273.106.203', 'Daftar Induk Dokumen Eksternal', 3, '00'],
            ['273.106.204', 'Usulan permintaan atau perubahan dokumen', 3, '00'],
            ['273.106.205', 'Evaluasi Kaji Ulang Dokumen', 3, '00'],
            ['273.106.206', 'Form Serah Terima Dokumen', 3, '00'],

            // === KELOMPOK KAJI ULANG TENDER (273.107) ===
            ['273.107', 'Kaji ulang permintaan tender kontrak, subkontrak pengujian, kalibrasi', 1, '00'],
            ['273.107.101', 'Pengecekan Antara Peralatan', 2, '00'],
            ['273.107.102', 'Penanganan Batu Timbang', 2, '00'],
            ['273.107.103', 'Penanganan Alat Gelas', 2, '00'],
            ['273.107.201', 'Form Evaluasi Hasil Kalibrasi', 3, '00'],
            ['273.107.202', 'Form Program Kalibrasi Alat', 3, '00'],
            ['273.107.203', 'Form Label Status Kalibrasi', 3, '00'],
            ['273.107.204', 'Form Data Verifikasi Timbangan', 3, '00'],
            ['273.107.205', 'Form Verifikasi Suhu (Oven atau Furnace atau Hotplate)', 3, '00'],
            ['273.107.206', 'Form Verifikasi Volume', 3, '00'],

            // === KELOMPOK REKRUTMEN (273.108) ===
            ['273.108', 'Rekrutmen dan Pelatihan', 1, '01'],
            ['273.108.201', 'Pelatihan dan pendampingan personil baru', 3, '00'],
            ['273.108.202', 'Evaluasi keefektifan pelatihan', 3, '00'],
            ['273.108.203', 'Form penilaian prestasi individu karyawan outsourcing TAD', 3, '00'],
        ];
    }
}
