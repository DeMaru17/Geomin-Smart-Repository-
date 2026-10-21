<?php

namespace Tests\Feature;

use App\Filament\Resources\DocumentDestructions\Pages\ListDocumentDestructions;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentDestruction;
use App\Models\DocumentDestructionGroup;
use App\Models\DocumentRevision;
use App\Models\User;
use App\Services\DocumentDestructionExportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class DocumentDestructionExcelExportTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = ['mysql', 'mysql_hris'];

    private DocumentDestructionExportService $service;
    private User $adminUser;
    private User $managerUser;
    private User $proposerUser;
    private DocumentCategory $category;
    private Department $department;
    private Document $document;
    private DocumentRevision $revision;
    private DocumentDestruction $destruction;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 10, 20, 10, 0, 0, 'Asia/Jakarta'));

        $this->service = new DocumentDestructionExportService();

        $this->adminUser = User::factory()->admin()->create([
            'name' => 'Admin GSR',
        ]);

        $this->managerUser = User::factory()->create([
            'name' => 'Manager Penjamin Mutu',
            'role' => 'manajemen',
            'jabatan' => 'manager',
        ]);

        $this->proposerUser = User::factory()->create([
            'name' => 'Pengendali Dokumen Maru',
            'role' => 'manajemen',
            'jabatan' => 'manajemen',
        ]);

        $this->category = DocumentCategory::factory()->create([
            'code' => 'PK',
            'name' => 'Prosedur Kerja',
        ]);

        $this->department = Department::factory()->create();

        $this->document = Document::factory()->create([
            'document_number' => '273.101.55',
            'title' => 'SOP Pengujian Sampel Mineral Logam',
            'category_id' => $this->category->id,
            'department_id' => $this->department->id,
            'is_external' => false,
            'status' => 'Active',
        ]);

        $this->revision = DocumentRevision::factory()->create([
            'document_id' => $this->document->id,
            'revision_number' => '01',
            'revision_date' => Carbon::create(2025, 3, 15),
            'status' => 'Published',
        ]);

        $this->destruction = DocumentDestruction::create([
            'bapd_number' => '001/LAB-BAPD/20/10/2026',
            'document_id' => $this->document->id,
            'proposer_id' => $this->proposerUser->id,
            'reason' => 'Metode analisis telah digantikan oleh standar ISO 17025 terbaru.',
            'method' => 'Penarikan Akses Digital',
            'status' => 'Disetujui',
            'approver_id' => $this->managerUser->id,
            'approved_at' => Carbon::create(2026, 10, 20, 10, 30, 0, 'Asia/Jakarta'),
            'created_at' => Carbon::create(2026, 10, 20, 8, 0, 0, 'Asia/Jakarta'),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * 1. An eligible approved BAPD can be exported as .xlsx.
     */
    public function test_eligible_approved_bapd_can_be_exported_as_xlsx(): void
    {
        $filePath = $this->service->export($this->destruction);

        $this->assertIsString($filePath);
        $this->assertFileExists($filePath);
        $this->assertStringEndsWith('.xlsx', $filePath);
        $this->assertGreaterThan(0, filesize($filePath));

        $filename = $this->service->getFilename($this->destruction);
        $this->assertStringStartsWith('BAPD_', $filename);
        $this->assertStringEndsWith('.xlsx', $filename);

        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }

    /**
     * 2. The output is a valid Excel workbook.
     */
    public function test_output_is_valid_excel_workbook(): void
    {
        $filePath = $this->service->export($this->destruction);
        $this->assertIsString($filePath);

        $spreadsheet = IOFactory::load($filePath);
        $this->assertInstanceOf(Spreadsheet::class, $spreadsheet);

        $sheet = $spreadsheet->getActiveSheet();
        $this->assertEquals('Sheet1', $sheet->getTitle());

        $spreadsheet->disconnectWorksheets();
        @unlink($filePath);
    }

    /**
     * 3. The official template's structure and key labels remain present.
     */
    public function test_template_structure_and_key_labels_remain_present(): void
    {
        $spreadsheet = $this->service->generateSpreadsheet($this->destruction);
        $this->assertInstanceOf(Spreadsheet::class, $spreadsheet);
        $sheet = $spreadsheet->getActiveSheet();

        // Header Form and Rev labels
        $this->assertEquals(': 273.106.207', $sheet->getCell('H2')->getValue());
        $this->assertEquals(': 00', $sheet->getCell('H3')->getValue());
        $this->assertEquals(': 20/10/2026', $sheet->getCell('H4')->getValue());

        // Title and Subtitle
        $this->assertEquals('BERITA ACARA PEMUSNAHAN DOKUMEN', $sheet->getCell('B5')->getValue());
        $this->assertEquals('Identitas Dokumen yang Dimusnahkan', $sheet->getCell('B7')->getValue());

        // 7 Table Headers at Row 8
        $this->assertEquals('No', $sheet->getCell('B8')->getValue());
        $this->assertEquals('No Dokumen', $sheet->getCell('C8')->getValue());
        $this->assertEquals('Judul Dokumen', $sheet->getCell('D8')->getValue());
        $this->assertEquals('Jenis Dokumen', $sheet->getCell('E8')->getValue());
        $this->assertEquals('Tanggal Terbit', $sheet->getCell('F8')->getValue());
        $this->assertEquals('No Revisi Terakhir', $sheet->getCell('G8')->getValue());
        $this->assertEquals('Cara Pemusnahan', $sheet->getCell('H8')->getValue());

        $spreadsheet->disconnectWorksheets();
    }

    /**
     * 4. Each of the seven table columns receives the correct mapped data.
     */
    public function test_each_of_seven_table_columns_receives_correct_mapped_data(): void
    {
        $spreadsheet = $this->service->generateSpreadsheet($this->destruction);
        $sheet = $spreadsheet->getActiveSheet();

        // Document row is row 9
        $this->assertEquals(1, $sheet->getCell('B9')->getValue());
        $this->assertEquals('273.101.55', $sheet->getCell('C9')->getValue());
        $this->assertEquals('SOP Pengujian Sampel Mineral Logam', $sheet->getCell('D9')->getValue());
        $this->assertEquals('Prosedur Kerja', $sheet->getCell('E9')->getValue());
        $this->assertEquals('15/03/2025', $sheet->getCell('F9')->getValue());
        $this->assertEquals('01', $sheet->getCell('G9')->getValue());
        $this->assertEquals('Penarikan Akses Digital', $sheet->getCell('H9')->getValue());

        $spreadsheet->disconnectWorksheets();
    }

    /**
     * 5. Multiple approved requests with the same local approval date are grouped together in one BAPD.
     */
    public function test_multiple_approved_requests_with_same_approval_date_are_grouped(): void
    {
        $doc2 = Document::factory()->create([
            'document_number' => '273.102.10',
            'title' => 'IK Preparasi Sampel Basah',
            'category_id' => $this->category->id,
            'department_id' => $this->department->id,
        ]);
        DocumentRevision::factory()->create([
            'document_id' => $doc2->id,
            'revision_number' => '02',
            'revision_date' => Carbon::create(2025, 4, 1),
            'status' => 'Published',
        ]);

        $doc3 = Document::factory()->create([
            'document_number' => '273.103.05',
            'title' => 'Formulir Hasil Analisis XRF',
            'category_id' => $this->category->id,
            'department_id' => $this->department->id,
        ]);
        DocumentRevision::factory()->create([
            'document_id' => $doc3->id,
            'revision_number' => '00',
            'revision_date' => Carbon::create(2025, 5, 10),
            'status' => 'Published',
        ]);

        // Request 2 approved at 11:15 on same date
        $destruction2 = DocumentDestruction::create([
            'document_id' => $doc2->id,
            'proposer_id' => $this->proposerUser->id,
            'reason' => 'Metode diganti',
            'method' => 'Penghancuran Dokumen Fisik/Cetak',
            'status' => 'Disetujui',
            'approver_id' => $this->managerUser->id,
            'approved_at' => Carbon::create(2026, 10, 20, 11, 15, 0, 'Asia/Jakarta'),
        ]);

        // Request 3 approved at 15:45 on same date
        $destruction3 = DocumentDestruction::create([
            'document_id' => $doc3->id,
            'proposer_id' => $this->proposerUser->id,
            'reason' => 'Formulir usang',
            'method' => 'Lainnya',
            'status' => 'Disetujui',
            'approver_id' => $this->managerUser->id,
            'approved_at' => Carbon::create(2026, 10, 20, 15, 45, 0, 'Asia/Jakarta'),
        ]);

        // All 3 requests should share the same DocumentDestructionGroup
        $this->assertEquals($this->destruction->document_destruction_group_id, $destruction2->document_destruction_group_id);
        $this->assertEquals($this->destruction->document_destruction_group_id, $destruction3->document_destruction_group_id);

        $group = $this->destruction->group;
        $this->assertNotNull($group);
        $this->assertEquals(3, $group->destructions()->count());

        // Exporting the destruction or group produces all 3 rows
        $spreadsheet = $this->service->generateSpreadsheet($this->destruction);
        $sheet = $spreadsheet->getActiveSheet();

        // Row 9 (Doc 1)
        $this->assertEquals(1, $sheet->getCell('B9')->getValue());
        $this->assertEquals('273.101.55', $sheet->getCell('C9')->getValue());
        $this->assertEquals('Penarikan Akses Digital', $sheet->getCell('H9')->getValue());

        // Row 10 (Doc 2)
        $this->assertEquals(2, $sheet->getCell('B10')->getValue());
        $this->assertEquals('273.102.10', $sheet->getCell('C10')->getValue());
        $this->assertEquals('IK Preparasi Sampel Basah', $sheet->getCell('D10')->getValue());
        $this->assertEquals('Penghancuran Dokumen Fisik/Cetak', $sheet->getCell('H10')->getValue());

        // Row 11 (Doc 3)
        $this->assertEquals(3, $sheet->getCell('B11')->getValue());
        $this->assertEquals('273.103.05', $sheet->getCell('C11')->getValue());
        $this->assertEquals('Formulir Hasil Analisis XRF', $sheet->getCell('D11')->getValue());
        $this->assertEquals('Lainnya', $sheet->getCell('H11')->getValue());

        $spreadsheet->disconnectWorksheets();
    }

    /**
     * 6. Requests approved on different local calendar dates are in separate BAPDs.
     */
    public function test_requests_approved_on_different_calendar_dates_are_separate(): void
    {
        $docOtherDate = Document::factory()->create([
            'document_number' => '273.104.01',
            'title' => 'SOP Kalibrasi Timbangan',
            'category_id' => $this->category->id,
            'department_id' => $this->department->id,
        ]);
        DocumentRevision::factory()->create([
            'document_id' => $docOtherDate->id,
            'revision_number' => '01',
            'revision_date' => Carbon::create(2025, 2, 1),
            'status' => 'Published',
        ]);

        $destructionNextDay = DocumentDestruction::create([
            'document_id' => $docOtherDate->id,
            'proposer_id' => $this->proposerUser->id,
            'reason' => 'Pemusnahan hari berikutnya',
            'method' => 'Penarikan Akses Digital',
            'status' => 'Disetujui',
            'approver_id' => $this->managerUser->id,
            'approved_at' => Carbon::create(2026, 10, 21, 9, 0, 0, 'Asia/Jakarta'),
        ]);

        $this->assertNotEquals(
            $this->destruction->document_destruction_group_id,
            $destructionNextDay->document_destruction_group_id
        );

        $group1 = $this->destruction->group;
        $group2 = $destructionNextDay->group;

        $this->assertEquals('2026-10-20', $group1->approval_date->toDateString());
        $this->assertEquals('2026-10-21', $group2->approval_date->toDateString());
        $this->assertNotEquals($group1->bapd_number, $group2->bapd_number);
    }

    /**
     * 7. Requests without valid approval timestamps are excluded from groups.
     */
    public function test_unapproved_requests_are_excluded_from_groups(): void
    {
        $pendingDestruction = DocumentDestruction::create([
            'document_id' => $this->document->id,
            'proposer_id' => $this->proposerUser->id,
            'reason' => 'Menunggu review',
            'method' => 'Penarikan Akses Digital',
            'status' => 'Pending',
            'approved_at' => null,
        ]);

        $rejectedDestruction = DocumentDestruction::create([
            'document_id' => $this->document->id,
            'proposer_id' => $this->proposerUser->id,
            'reason' => 'Ditolak',
            'method' => 'Penarikan Akses Digital',
            'status' => 'Ditolak',
            'approved_at' => null,
        ]);

        $this->assertNull($pendingDestruction->document_destruction_group_id);
        $this->assertNull($rejectedDestruction->document_destruction_group_id);
    }

    /**
     * 8. Timezone boundaries and local calendar date transitions across midnight.
     */
    public function test_timezone_boundaries_are_handled_correctly(): void
    {
        // 23:59 on 2026-10-20 vs 00:01 on 2026-10-21 in configured timezone (Asia/Jakarta)
        $lateNight = Carbon::create(2026, 10, 20, 23, 59, 0, 'Asia/Jakarta');
        $earlyMorning = Carbon::create(2026, 10, 21, 0, 1, 0, 'Asia/Jakarta');

        $doc1 = Document::factory()->create();
        DocumentRevision::factory()->create(['document_id' => $doc1->id, 'status' => 'Published']);

        $destruction1 = DocumentDestruction::create([
            'document_id' => $doc1->id,
            'proposer_id' => $this->proposerUser->id,
            'reason' => 'Uji batas akhir hari',
            'method' => 'Penarikan Akses Digital',
            'status' => 'Disetujui',
            'approver_id' => $this->managerUser->id,
            'approved_at' => $lateNight,
        ]);

        $doc2 = Document::factory()->create();
        DocumentRevision::factory()->create(['document_id' => $doc2->id, 'status' => 'Published']);

        $destruction2 = DocumentDestruction::create([
            'document_id' => $doc2->id,
            'proposer_id' => $this->proposerUser->id,
            'reason' => 'Uji batas awal hari baru',
            'method' => 'Penarikan Akses Digital',
            'status' => 'Disetujui',
            'approver_id' => $this->managerUser->id,
            'approved_at' => $earlyMorning,
        ]);

        $this->assertEquals('2026-10-20', $destruction1->group->approval_date->toDateString());
        $this->assertEquals('2026-10-21', $destruction2->group->approval_date->toDateString());
        $this->assertNotEquals($destruction1->document_destruction_group_id, $destruction2->document_destruction_group_id);
    }

    /**
     * 9. Additional rows preserve signature section and dynamic row positioning.
     */
    public function test_additional_rows_preserve_signature_section_and_labels(): void
    {
        for ($i = 2; $i <= 5; $i++) {
            $doc = Document::factory()->create([
                'document_number' => "273.10{$i}.99",
                'title' => "Dokumen Uji {$i}",
                'category_id' => $this->category->id,
                'department_id' => $this->department->id,
            ]);
            DocumentRevision::factory()->create([
                'document_id' => $doc->id,
                'revision_number' => str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'revision_date' => Carbon::create(2025, 1, $i),
                'status' => 'Published',
            ]);

            DocumentDestruction::create([
                'document_id' => $doc->id,
                'proposer_id' => $this->proposerUser->id,
                'reason' => "Uji alasan {$i}",
                'method' => $i % 2 === 0 ? 'Penarikan Akses Digital' : 'Penghancuran Dokumen Fisik/Cetak',
                'status' => 'Disetujui',
                'approver_id' => $this->managerUser->id,
                'approved_at' => Carbon::create(2026, 10, 20, 10, 30 + $i, 0, 'Asia/Jakarta'),
            ]);
        }

        $spreadsheet = $this->service->generateSpreadsheet($this->destruction);
        $sheet = $spreadsheet->getActiveSheet();

        // 5 documents should occupy rows 9 to 13
        for ($r = 9; $r <= 13; $r++) {
            $this->assertEquals($r - 8, $sheet->getCell("B{$r}")->getValue());
        }

        // Signature section must be below row 13
        $nameRow = null;
        $dateRow = null;
        for ($r = 14; $r <= $sheet->getHighestRow(); $r++) {
            $val = (string) $sheet->getCell("B{$r}")->getValue();
            if (str_starts_with(trim($val), 'Nama Pengendali Dokumen')) {
                $nameRow = $r;
            }
            if ($nameRow !== null && str_starts_with(trim($val), 'Tanggal')) {
                $dateRow = $r;
                break;
            }
        }

        $this->assertNotNull($nameRow, 'Nama Pengendali Dokumen row should be found below data rows');
        $this->assertNotNull($dateRow, 'Tanggal row should be found below name row');
        $this->assertGreaterThan(13, $nameRow);
        $this->assertGreaterThan($nameRow, $dateRow);

        // Verify signature labels and names
        $this->assertStringContainsString('Nama Pengendali Dokumen: Pengendali Dokumen Maru', (string) $sheet->getCell("B{$nameRow}")->getValue());
        $this->assertStringContainsString('Nama Penanggung Jawab Mutu: Manager Penjamin Mutu', (string) $sheet->getCell("G{$nameRow}")->getValue());

        // Verify dates
        $this->assertStringContainsString('Tanggal : 20/10/2026', (string) $sheet->getCell("B{$dateRow}")->getValue());
        $this->assertStringContainsString('Tanggal : 20/10/2026', (string) $sheet->getCell("G{$dateRow}")->getValue());

        $spreadsheet->disconnectWorksheets();
    }

    /**
     * 10. Historical group finalization freezes snapshot and prevents alteration by late approvals.
     */
    public function test_group_finalization_freezes_snapshot_and_isolates_late_approvals(): void
    {
        $group = $this->destruction->group;
        $this->assertNotNull($group);

        // Finalize group with 1 document
        $group->finalize();
        $this->assertTrue($group->isFinalized());
        $this->assertNotNull($group->snapshot_data);
        $this->assertCount(1, $group->snapshot_data);

        // Modify original document title after finalization
        $this->document->update(['title' => 'Judul yang Diubah Pasca-Finalisasi']);

        // Exporting finalized group still renders the snapshot title
        $spreadsheet = $this->service->generateSpreadsheet($group);
        $sheet = $spreadsheet->getActiveSheet();
        $this->assertEquals('SOP Pengujian Sampel Mineral Logam', $sheet->getCell('D9')->getValue());
        $spreadsheet->disconnectWorksheets();

        // Late approval on the same date creates a new open group and does NOT alter the finalized group
        $docLate = Document::factory()->create(['title' => 'Dokumen Terlambat Disetujui']);
        DocumentRevision::factory()->create(['document_id' => $docLate->id, 'status' => 'Published']);

        $destructionLate = DocumentDestruction::create([
            'document_id' => $docLate->id,
            'proposer_id' => $this->proposerUser->id,
            'reason' => 'Disetujui sore hari',
            'method' => 'Penarikan Akses Digital',
            'status' => 'Disetujui',
            'approver_id' => $this->managerUser->id,
            'approved_at' => Carbon::create(2026, 10, 20, 16, 0, 0, 'Asia/Jakarta'),
        ]);

        $this->assertNotEquals($group->id, $destructionLate->document_destruction_group_id);
        $this->assertCount(1, $group->destructions);
        $this->assertEquals(1, $destructionLate->group->destructions()->count());
    }

    /**
     * 11. Existing request data attributes and IDs are preserved.
     */
    public function test_request_identity_and_attributes_preserved(): void
    {
        $id = $this->destruction->id;
        $bapdNumber = $this->destruction->bapd_number;
        $reason = $this->destruction->reason;

        $this->destruction->refresh();

        $this->assertEquals($id, $this->destruction->id);
        $this->assertEquals($bapdNumber, $this->destruction->bapd_number);
        $this->assertEquals($reason, $this->destruction->reason);
        $this->assertEquals('Disetujui', $this->destruction->status);
        $this->assertEquals($this->proposerUser->id, $this->destruction->proposer_id);
        $this->assertEquals($this->managerUser->id, $this->destruction->approver_id);
    }

    /**
     * 12. Row-level Cetak BAPD action is removed; header-level action is available for authorized users.
     */
    public function test_row_level_export_action_is_removed_and_header_action_available(): void
    {
        $pendingDestruction = DocumentDestruction::create([
            'document_id' => $this->document->id,
            'proposer_id' => $this->proposerUser->id,
            'reason' => 'Menunggu review',
            'method' => 'Penarikan Akses Digital',
            'status' => 'Pending',
        ]);

        // Row-level cetak_bapd must NOT exist
        Livewire::actingAs($this->adminUser)
            ->test(ListDocumentDestructions::class)
            ->assertTableActionDoesNotExist('cetak_bapd')
            ->assertTableActionExists('cetak_bapd_tanggal');

        // Personil user cannot see header action
        $personilUser = User::factory()->create(['role' => 'personil']);
        Livewire::actingAs($personilUser)
            ->test(ListDocumentDestructions::class)
            ->assertTableActionHidden('cetak_bapd_tanggal');

        // Manager reviews and approves
        Livewire::actingAs($this->managerUser)
            ->test(ListDocumentDestructions::class)
            ->callTableAction('review', $pendingDestruction, data: [
                'decision' => 'Setujui',
            ])
            ->assertHasNoTableActionErrors();

        $pendingDestruction->refresh();
        $this->assertEquals('Disetujui', $pendingDestruction->status);
        $this->assertNotNull($pendingDestruction->document_destruction_group_id);
    }

    /**
     * 13. Calendar availability indicators are derived from persisted eligible groups.
     */
    public function test_calendar_availability_indicators(): void
    {
        $component = Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\BapdExportModal::class);

        $availableDates = $component->instance()->getAvailableDates();

        // 2026-10-20 has approved BAPD
        $this->assertArrayHasKey('2026-10-20', $availableDates);
        $this->assertGreaterThanOrEqual(1, $availableDates['2026-10-20']);

        // 2026-10-01 has no BAPD
        $this->assertArrayNotHasKey('2026-10-01', $availableDates);
    }

    /**
     * 14. Selecting a date displays the correct group and details.
     */
    public function test_selecting_date_displays_correct_group_and_multiple_groups(): void
    {
        // Add a second group on the same date (finalized + open)
        $doc2 = Document::factory()->create(['title' => 'Dokumen Kedua']);
        DocumentRevision::factory()->create(['document_id' => $doc2->id, 'status' => 'Published']);

        // Finalize first group
        $this->destruction->group->finalize();

        // Second group created on late approval
        $destruction2 = DocumentDestruction::create([
            'document_id' => $doc2->id,
            'proposer_id' => $this->proposerUser->id,
            'reason' => 'Kelompok kedua hari ini',
            'method' => 'Lainnya',
            'status' => 'Disetujui',
            'approver_id' => $this->managerUser->id,
            'approved_at' => Carbon::create(2026, 10, 20, 16, 0, 0, 'Asia/Jakarta'),
        ]);

        $component = Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\BapdExportModal::class)
            ->call('selectDate', '2026-10-20');

        $groups = $component->instance()->getSelectedDateGroups();
        $this->assertCount(2, $groups);

        // Selecting a date without BAPD returns empty collection
        $component->call('selectDate', '2026-10-01');
        $emptyGroups = $component->instance()->getSelectedDateGroups();
        $this->assertCount(0, $emptyGroups);
    }

    /**
     * 15. Month navigation retrieves correct availability for the target month.
     */
    public function test_month_navigation_retrieves_correct_availability(): void
    {
        $component = Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\BapdExportModal::class);

        // Current month is October 2026
        $this->assertEquals(10, $component->get('currentMonth'));

        // Navigate to November 2026
        $component->call('nextMonth');
        $this->assertEquals(11, $component->get('currentMonth'));
        $availableDatesNov = $component->instance()->getAvailableDates();
        $this->assertEmpty($availableDatesNov);

        // Navigate back to October 2026
        $component->call('previousMonth');
        $this->assertEquals(10, $component->get('currentMonth'));
        $availableDatesOct = $component->instance()->getAvailableDates();
        $this->assertNotEmpty($availableDatesOct);
    }

    /**
     * 16. Searching by BAPD number, document number, and document title returns correct group without duplicate rows.
     */
    public function test_searching_bapd_groups_by_number_and_document_details(): void
    {
        $docA = Document::factory()->create([
            'document_number' => 'DOC-XYZ-777',
            'title' => 'SOP Kalibrasi Densitas Mineral',
        ]);
        DocumentRevision::factory()->create(['document_id' => $docA->id, 'status' => 'Published']);

        $docB = Document::factory()->create([
            'document_number' => 'DOC-XYZ-888',
            'title' => 'IK Preparasi Sampel Mineral',
        ]);
        DocumentRevision::factory()->create(['document_id' => $docB->id, 'status' => 'Published']);

        $destA = DocumentDestruction::create([
            'document_id' => $docA->id,
            'proposer_id' => $this->proposerUser->id,
            'reason' => 'Pengujian pencarian A',
            'method' => 'Penarikan Akses Digital',
            'status' => 'Disetujui',
            'approver_id' => $this->managerUser->id,
            'approved_at' => Carbon::create(2026, 10, 22, 10, 0, 0, 'Asia/Jakarta'),
        ]);

        $destB = DocumentDestruction::create([
            'document_id' => $docB->id,
            'proposer_id' => $this->proposerUser->id,
            'reason' => 'Pengujian pencarian B',
            'method' => 'Penarikan Akses Digital',
            'status' => 'Disetujui',
            'approver_id' => $this->managerUser->id,
            'approved_at' => Carbon::create(2026, 10, 22, 11, 0, 0, 'Asia/Jakarta'),
        ]);

        $group22 = $destA->group;
        $this->assertEquals($group22->id, $destB->document_destruction_group_id);

        $component = Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\BapdExportModal::class)
            ->set('activeTab', 'list');

        // Search by document title
        $component->set('search', 'Densitas Mineral');
        $resultsTitle = $component->instance()->getSearchResults();
        $this->assertCount(1, $resultsTitle);
        $this->assertEquals($group22->id, $resultsTitle->first()->id);

        // Search by document number
        $component->set('search', 'DOC-XYZ-888');
        $resultsNum = $component->instance()->getSearchResults();
        $this->assertCount(1, $resultsNum);
        $this->assertEquals($group22->id, $resultsNum->first()->id);

        // Search matching multiple documents in the same group still returns only 1 group entry
        $component->set('search', 'DOC-XYZ');
        $resultsMulti = $component->instance()->getSearchResults();
        $this->assertCount(1, $resultsMulti);
        $this->assertEquals($group22->id, $resultsMulti->first()->id);

        // Search by BAPD number
        $component->set('search', $group22->bapd_number);
        $resultsBapd = $component->instance()->getSearchResults();
        $this->assertCount(1, $resultsBapd);
        $this->assertEquals($group22->id, $resultsBapd->first()->id);
    }

    /**
     * 17. Download via dedicated controller route works for authorized users and rejects unauthorized users.
     */
    public function test_download_route_authorization_and_response(): void
    {
        $group = $this->destruction->group;

        // Unauthorized personil gets 403
        $personil = User::factory()->create(['role' => 'personil']);
        $response403 = $this->actingAs($personil)->get(route('bapd.download', ['id' => $group->id]));
        $response403->assertStatus(403);

        // Authorized admin gets 200 with Excel download
        $response200 = $this->actingAs($this->adminUser)->get(route('bapd.download', ['id' => $group->id]));
        $response200->assertStatus(200);
        $response200->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /**
     * 18. Calendar navigation and searching do not alter request statuses or group membership.
     */
    public function test_ui_interactions_do_not_alter_data(): void
    {
        $initialStatus = $this->destruction->status;
        $initialGroupId = $this->destruction->document_destruction_group_id;
        $initialGroupCount = DocumentDestructionGroup::count();
        $initialDestructionCount = DocumentDestruction::count();

        $component = Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\BapdExportModal::class)
            ->call('previousMonth')
            ->call('nextMonth')
            ->call('selectDate', '2026-10-20')
            ->set('activeTab', 'list')
            ->set('search', 'Random Search Term');

        $this->destruction->refresh();
        $this->assertEquals($initialStatus, $this->destruction->status);
        $this->assertEquals($initialGroupId, $this->destruction->document_destruction_group_id);
        $this->assertEquals($initialGroupCount, DocumentDestructionGroup::count());
        $this->assertEquals($initialDestructionCount, DocumentDestruction::count());
    }

    /**
     * 19. Exporting does not modify the original Excel template.
     */
    public function test_exporting_does_not_modify_original_template(): void
    {
        $templatePath = $this->service->getTemplatePath();
        $hashBefore = md5_file($templatePath);

        $filePath = $this->service->export($this->destruction);
        $hashAfter = md5_file($templatePath);

        $this->assertEquals($hashBefore, $hashAfter, 'Original Excel template must not be modified by export operations.');

        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }

    /**
     * 20. Missing template returns false gracefully.
     */
    public function test_missing_template_returns_false(): void
    {
        $mockService = new class extends DocumentDestructionExportService {
            public function getTemplatePath(): string
            {
                return storage_path('app/templates/non_existent_template.xlsx');
            }
        };

        $result = $mockService->export($this->destruction);
        $this->assertFalse($result);

        $spreadsheet = $mockService->generateSpreadsheet($this->destruction);
        $this->assertFalse($spreadsheet);
    }
}
