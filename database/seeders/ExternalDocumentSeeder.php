<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\DocumentRevision;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ExternalDocumentSeeder extends Seeder
{
    /**
     * Seed dokumen eksternal lab ISO Geomin.
     * Dokumen eksternal tidak terikat departemen internal dan tidak dikategorikan PK/IK/F.
     */
    public function run(): void
    {
        $uploaderId = 1; // Admin user

        $documentsRaw = $this->getDocumentsData();

        foreach ($documentsRaw as $doc) {
            [$docNumber, $title, $pubYear, $remarks] = $doc;

            $document = Document::updateOrCreate(
                ['document_number' => $docNumber],
                [
                    'title' => $title,
                    'category_id' => null,
                    'department_id' => null,
                    'is_external' => true,
                    'publication_year' => $pubYear,
                    'remarks' => $remarks ?: 'Dokumen Eksternal',
                    'retention_period_months' => 60,
                    'status' => 'Active',
                ],
            );

            // Create Published revision if none exists
            $existingRevision = $document->revisions()
                ->whereIn('status', ['Published', 'Terbit'])
                ->first();

            if (! $existingRevision) {
                $monthsAgo = rand(2, 24);
                $revisionDate = Carbon::today()->subMonths($monthsAgo)->subDays(rand(0, 28));

                DocumentRevision::create([
                    'document_id' => $document->id,
                    'revision_number' => '00',
                    'file_path' => 'documents/external/' . Str::slug($docNumber) . '.pdf',
                    'status' => 'Published',
                    'revision_date' => $revisionDate,
                    'change_summary' => 'Dokumen eksternal - ' . $title,
                    'qr_token' => Str::uuid()->toString(),
                    'uploader_id' => $uploaderId,
                ]);
            }
        }

        $this->command->info('Seeded ' . count($documentsRaw) . ' external documents with Published revisions.');
    }

    /**
     * Data dokumen eksternal.
     * Format: [document_number, title, publication_year, remarks]
     */
    private function getDocumentsData(): array
    {
        return [
            ['E001', 'Methods for Chemical Analysis of Bauxite Ore JIS M 8361-1968', 2000, 'Revisi: 10-12-86'],
            ['E002', "The Bead Cookbook for Perl'X and Minifuse Edition Third", 2004, null],
            ['E003', 'Sample Preparation Equipment, Press System; Some Theory', null, null],
            ['E004', 'CookBook Recipes', null, null],
            ['E005', 'ASTM Designation: E2857-11 Standard Guide For Validating Analytical Method', 2013, null],
            ['E006', 'KAN-G-06 KAN Guide on Measurement Assurance', 2008, null],
            ['E007', 'KAN-RLK-01 Persyaratan Tambahan Implementasi ISO/IEC 17025 Untuk Laboratorium Kalibrasi', 2011, null],
            ['E008', 'Sartorius Recommendations for Handling, Storage and Cleaning of Weights and Mass Standards', null, null],
            ['E009', 'Memmert, Operating Instruction', 1998, null],
            ['E010', 'Type 30400, Thermolyne Furnace Operation Manual and Part List Series 1262', null, null],
            ['E011', 'AR807 Digital Thermometer Humidity', null, null],
            ['E012', 'SNI 13-3496-1994 Cara preparasi contoh bahan galian secara umum untuk analisis kimia dan uji sifat fisik di laboratorium', 1994, null],
            ['E013', 'SNI 13-3610-1994 Penentuan emas dan perak di dalam batuan dengan fire assay', 1994, null],
            ['E014', 'SNI 13-6974-2003 Percontohan batuan sulfida - Penentuan kadar Pb, Cu, Zn, Fe, Mn dan Cd dengan spektrofotometer serapan atom (SSA)', 2003, null],
            ['E015', 'Prosedur Kerja (PK) Penunjukan Langsung Nomor: 600.101', 2014, 'Revisi: 20/10/2014'],
            ['E016', 'Prosedur Kerja (PK) Pemilihan Langsung Nomor: 600.102', 2014, 'Revisi: 20/10/2014'],
            ['E017', 'Prosedur Kerja (PK) Pelelangan Nomor: 600.103', 2014, 'Revisi: 20/10/2014'],
            ['E018', 'Prosedur Kerja (PK) Tim Pengadaan Nomor: 600.109', 2014, 'Revisi: 20/10/2014'],
            ['E019', 'PP Nomor 63 Tahun 2000 Keselamatan dan Kesehatan Terhadap Pemanfaatan Radiasi Pengion (BAPETEN)', 2000, null],
            ['E020', "K2 KATANAX Manual User's Automatic Fluxer K2 KATANAX", 2009, null],
            ['E021', "Manual User's Front Tray Installation", null, null],
            ['E022', 'Spectrophotometer Model U-2900 Manual Instruction Auto Sipper', 2008, null],
            ['E023', 'Spectrophotometer Model AS-1010 Manual Instruction Auto Sampler', 2008, null],
            ['E024', 'Furnace Thermolyne Type: 30400 Manual Operation and Parts List', 2009, null],
            ['E025', 'A Text Book Of Fire Assaying By Edward E. Bugbee', 1922, null],
            ['E026', 'A Manual Of Fire Assaying By Charles Herman Fulton', 1911, null],
            ['E027', 'Maintenance dan Trouble Shooting AAS Pusat Pengembangan KK & Hiperkes Depnakertrans Oleh Tasrif', 2006, null],
            ['E028', 'User Guide Agilent 240/280 Series AA', 2012, null],
            ['E029', 'User Guide Agilent Graphite Tube GTA 120', 2012, null],
            ['E030', 'Pelatihan Estimasi Ketidakpastian Pengukuran Oleh BSN', 2013, null],
            ['E031', 'Pelatihan Teknik Evaluasi Hasil QC LAB dan Kompetensi Analis Oleh SPIN', 2015, null],
            ['E032', 'Dispensette BRAND Manual Book', null, null],
            ['E033', 'Dispensette BRAND For Application', 2004, null],
            ['E034', 'Persyaratan Tambahan untuk Akreditasi Laboratorium Pengujian Kimia dan Biologi Oleh KAN', 2004, null],
            ['E035', 'Standard Specification for Reagent Water Reference From ASTM', null, null],
            ['E036', 'Purified Water Reference From Wikipedia', null, null],
            ['E037', 'Specification and Test Methods Water for Analytical Laboratory From British Standard', null, null],
            ['E038', 'Akte Notaris KEMENKUMHAM / AHA-AHU.01.03-0927518 No 67, Tanggal 31 Maret 2015', 2015, null],
            ['E039', 'Validasi Metode Pengujian Kimia Oleh BSN', 2013, null],
            ['E040', 'Pemahaman SNI ISO/IEC 17025:2008 Oleh BSN', 2010, null],
            ['E041', 'Methods for Sampling of Bauxite Ores JIS M 8110-1975', 2000, null],
        ];
    }
}
