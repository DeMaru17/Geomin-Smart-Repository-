<?php

namespace Tests\Feature;

use App\Filament\Resources\QualityRecords\Pages\ListQualityRecords;
use App\Livewire\BaprExportModal;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\QualityRecord;
use App\Models\QualityRecordDestructionGroup;
use App\Models\User;
use App\Services\QualityRecordDestructionExportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class QualityRecordDestructionTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = ['mysql', 'mysql_hris'];

    private User $adminUser;
    private User $managerUser;
    private User $staffUser;
    private DocumentCategory $formCategory;
    private Department $department;
    private Document $formDocument;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->admin()->create([
            'name' => 'Admin GSR Lab',
        ]);

        $this->managerUser = User::factory()->create([
            'name' => 'Dr. Lab Manager M.Si',
            'role' => 'manajemen',
            'jabatan' => 'manager',
        ]);

        $this->staffUser = User::factory()->create([
            'name' => 'Staff Pengendali Rekaman',
            'role' => 'personil',
            'jabatan' => 'analis',
        ]);

        $this->formCategory = DocumentCategory::factory()->create([
            'code' => 'F',
            'name' => 'Formulir',
        ]);

        $this->department = Department::factory()->create([
            'name' => 'Laboratorium Pengujian',
        ]);

        $this->formDocument = Document::factory()->create([
            'document_number' => '273.101.201',
            'title' => 'Formulir Hasil Analisa Kadar Logam',
            'category_id' => $this->formCategory->id,
            'department_id' => $this->department->id,
            'retention_period_months' => 36,
            'status' => 'Published',
        ]);
    }

    /**
     * Helper to create an expired quality record.
     */
    private function createExpiredRecord(array $attributes = []): QualityRecord
    {
        return QualityRecord::create(array_merge([
            'document_id' => $this->formDocument->id,
            'record_name' => 'Rekaman Uji Kadar Nikel Batch #01',
            'record_date' => Carbon::now()->subMonths(40)->toDateString(),
            'confidentiality' => 'Biasa',
            'physical_location' => 'Binder Lab 01',
            'file_path' => 'quality-records/sample.pdf',
            'status' => 'Aktif',
            'uploader_id' => $this->adminUser->id,
        ], $attributes));
    }

    public function test_quality_record_calculates_expiration_and_identifies_expired_records(): void
    {
        $record = $this->createExpiredRecord();

        $this->assertTrue($record->isExpired());
        $this->assertEquals(
            Carbon::parse($record->record_date)->addMonths(36)->toDateString(),
            $record->expiration_date->toDateString()
        );
    }

    public function test_destroy_action_updates_record_and_groups_into_bapr(): void
    {
        $record = $this->createExpiredRecord();

        $this->actingAs($this->adminUser);

        Livewire::test(ListQualityRecords::class)
            ->set('activeTab', 'kedaluwarsa')
            ->callTableAction('destroy', $record, [
                'method' => 'Dicacah',
            ]);

        $record->refresh();

        $this->assertEquals('Dimusnahkan', $record->status);
        $this->assertEquals('Dicacah', $record->destruction_method);
        $this->assertEquals($this->adminUser->id, $record->destruction_operator_id);
        $this->assertNotNull($record->quality_record_destruction_group_id);

        $group = $record->destructionGroup;
        $this->assertNotNull($group);
        $this->assertStringContainsString('/LAB-BAPR/', $group->bapr_number);
        $this->assertEquals(now()->toDateString(), $group->destruction_date->toDateString());
    }

    public function test_bulk_destroy_groups_multiple_records_into_single_bapr(): void
    {
        $rec1 = $this->createExpiredRecord(['record_name' => 'Rekaman Batch 1']);
        $rec2 = $this->createExpiredRecord(['record_name' => 'Rekaman Batch 2']);
        $rec3 = $this->createExpiredRecord(['record_name' => 'Rekaman Batch 3']);

        $this->actingAs($this->adminUser);

        Livewire::test(ListQualityRecords::class)
            ->set('activeTab', 'kedaluwarsa')
            ->callTableBulkAction('destroy', [$rec1, $rec2, $rec3], [
                'method' => 'Dihapus dari server & backup',
            ]);

        $rec1->refresh();
        $rec2->refresh();
        $rec3->refresh();

        $this->assertEquals('Dimusnahkan', $rec1->status);
        $this->assertEquals('Dimusnahkan', $rec2->status);
        $this->assertEquals('Dimusnahkan', $rec3->status);

        $this->assertNotNull($rec1->quality_record_destruction_group_id);
        $this->assertEquals($rec1->quality_record_destruction_group_id, $rec2->quality_record_destruction_group_id);
        $this->assertEquals($rec1->quality_record_destruction_group_id, $rec3->quality_record_destruction_group_id);

        $group = $rec1->destructionGroup;
        $this->assertEquals(3, $group->records()->count());
        $this->assertEquals('Dihapus dari server & backup', $group->method);
    }

    public function test_bapr_number_generation_sequence(): void
    {
        $date = Carbon::parse('2026-10-21');
        $group1 = QualityRecordDestructionGroup::create([
            'bapr_number' => QualityRecordDestructionGroup::generateBaprNumber($date),
            'destruction_date' => $date->toDateString(),
            'method' => 'Dicacah',
            'created_at' => $date,
        ]);

        $group2 = QualityRecordDestructionGroup::create([
            'bapr_number' => QualityRecordDestructionGroup::generateBaprNumber($date),
            'destruction_date' => $date->toDateString(),
            'method' => 'Dicacah',
            'created_at' => $date,
        ]);

        $this->assertEquals('001/LAB-BAPR/21/10/2026', $group1->bapr_number);
        $this->assertEquals('002/LAB-BAPR/21/10/2026', $group2->bapr_number);
    }

    public function test_bapr_calendar_availability_and_date_selection(): void
    {
        $this->actingAs($this->adminUser);

        $dateWithBapr = '2026-10-15';
        $group = QualityRecordDestructionGroup::create([
            'bapr_number' => '001/LAB-BAPR/15/10/2026',
            'destruction_date' => $dateWithBapr,
            'method' => 'Dicacah',
        ]);

        $rec = $this->createExpiredRecord([
            'status' => 'Dimusnahkan',
            'quality_record_destruction_group_id' => $group->id,
            'destruction_date' => $dateWithBapr,
        ]);

        $component = Livewire::test(BaprExportModal::class)
            ->set('currentYear', 2026)
            ->set('currentMonth', 10);

        $availableDates = $component->instance()->getAvailableDates();
        $this->assertArrayHasKey($dateWithBapr, $availableDates);
        $this->assertArrayNotHasKey('2026-10-16', $availableDates);

        $component->call('selectDate', $dateWithBapr);
        $selectedGroups = $component->instance()->getSelectedDateGroups();

        $this->assertCount(1, $selectedGroups);
        $this->assertEquals($group->id, $selectedGroups->first()->id);
    }

    public function test_bapr_search_functionality(): void
    {
        $this->actingAs($this->adminUser);

        $group = QualityRecordDestructionGroup::create([
            'bapr_number' => '042/LAB-BAPR/20/10/2026',
            'destruction_date' => '2026-10-20',
            'method' => 'Dihapus dari server & backup',
        ]);

        $rec = $this->createExpiredRecord([
            'record_name' => 'Laporan Geokimia Emas Proyek Arinem',
            'status' => 'Dimusnahkan',
            'quality_record_destruction_group_id' => $group->id,
            'destruction_date' => '2026-10-20',
        ]);

        $component = Livewire::test(BaprExportModal::class)
            ->set('activeTab', 'list')
            ->set('search', '042/LAB-BAPR');

        $results = $component->instance()->getSearchResults();
        $this->assertCount(1, $results);
        $this->assertEquals($group->id, $results->first()->id);

        // Search by record title
        $component->set('search', 'Geokimia Emas');
        $results = $component->instance()->getSearchResults();
        $this->assertCount(1, $results);
        $this->assertEquals($group->id, $results->first()->id);

        // Search by document number
        $component->set('search', '273.101.201');
        $results = $component->instance()->getSearchResults();
        $this->assertCount(1, $results);
        $this->assertEquals($group->id, $results->first()->id);
    }

    public function test_bapr_excel_export_service_generates_valid_spreadsheet(): void
    {
        $group = QualityRecordDestructionGroup::create([
            'bapr_number' => '001/LAB-BAPR/21/10/2026',
            'destruction_date' => '2026-10-21',
            'method' => 'Dihapus dari server & backup',
            'destroyed_by_id' => $this->adminUser->id,
            'acknowledged_by_id' => $this->managerUser->id,
        ]);

        $rec1 = $this->createExpiredRecord([
            'record_name' => 'Rekaman Mutu Kalibrasi Timbangan',
            'record_date' => '2022-01-10',
            'status' => 'Dimusnahkan',
            'quality_record_destruction_group_id' => $group->id,
            'destruction_date' => '2026-10-21',
            'destruction_method' => 'Dihapus dari server & backup',
            'destruction_operator_id' => $this->adminUser->id,
        ]);

        $service = new QualityRecordDestructionExportService();
        $spreadsheet = $service->generateSpreadsheet($group);

        $this->assertInstanceOf(Spreadsheet::class, $spreadsheet);

        $sheet = $spreadsheet->getActiveSheet();

        // Check header date H4
        $this->assertEquals(': 21/10/2026', trim($sheet->getCell('H4')->getValue()));

        // Check row 9 values
        $this->assertEquals(1, $sheet->getCell('B9')->getValue());
        $this->assertEquals('273.101.201', $sheet->getCell('C9')->getValue());
        $this->assertEquals('10/01/2022', $sheet->getCell('D9')->getValue());
        $this->assertEquals('36 Bulan', $sheet->getCell('F9')->getValue());
        $this->assertEquals('21/10/2026', $sheet->getCell('G9')->getValue());
        $this->assertEquals('Dihapus dari server & backup', $sheet->getCell('H9')->getValue());

        // Check signatures dynamically located
        $highestRow = $sheet->getHighestRow();
        $foundPengendali = false;
        $foundManager = false;

        for ($r = 9; $r <= $highestRow; $r++) {
            $valB = (string) $sheet->getCell("B{$r}")->getValue();
            $valG = (string) $sheet->getCell("G{$r}")->getValue();

            if (str_contains($valB, 'Nama Pengendali Rekaman')) {
                $foundPengendali = true;
                $this->assertStringContainsString($this->adminUser->name, $valB);
            }
            if (str_contains($valG, 'Nama Penanggung Jawab Mutu')) {
                $foundManager = true;
                $this->assertStringContainsString($this->managerUser->name, $valG);
            }
        }

        $this->assertTrue($foundPengendali, 'Nama Pengendali Rekaman harus ditemukan di blok tanda tangan.');
        $this->assertTrue($foundManager, 'Nama Penanggung Jawab Mutu harus ditemukan di blok tanda tangan.');
    }

    public function test_bapr_download_endpoint_authorization(): void
    {
        $group = QualityRecordDestructionGroup::create([
            'bapr_number' => '001/LAB-BAPR/21/10/2026',
            'destruction_date' => '2026-10-21',
            'method' => 'Dicacah',
            'destroyed_by_id' => $this->adminUser->id,
        ]);

        $this->createExpiredRecord([
            'status' => 'Dimusnahkan',
            'quality_record_destruction_group_id' => $group->id,
            'destruction_date' => '2026-10-21',
        ]);

        // Unauthorized user gets 403
        $this->actingAs($this->staffUser);
        $response = $this->get(route('bapr.download', $group->id));
        $response->assertStatus(403);

        // Admin gets 200 with download
        $this->actingAs($this->adminUser);
        $response = $this->get(route('bapr.download', $group->id));
        $response->assertStatus(200);
        $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));
    }

    public function test_bapr_group_finalization_creates_frozen_snapshot(): void
    {
        $group = QualityRecordDestructionGroup::create([
            'bapr_number' => '099/LAB-BAPR/21/10/2026',
            'destruction_date' => '2026-10-21',
            'method' => 'Dicacah',
            'destroyed_by_id' => $this->adminUser->id,
            'acknowledged_by_id' => $this->managerUser->id,
        ]);

        $rec = $this->createExpiredRecord([
            'record_name' => 'Rekaman Snapshot Test',
            'status' => 'Dimusnahkan',
            'quality_record_destruction_group_id' => $group->id,
            'destruction_date' => '2026-10-21',
        ]);

        $this->assertFalse($group->isFinalized());

        $group->finalize();

        $group->refresh();
        $this->assertTrue($group->isFinalized());
        $this->assertNotNull($group->snapshot_data);
        $this->assertCount(1, $group->snapshot_data);
        $this->assertEquals('Rekaman Snapshot Test', $group->snapshot_data[0]['record_name']);
        $this->assertEquals($this->adminUser->name, $group->snapshot_data[0]['destroyed_by_name']);
        $this->assertEquals($this->managerUser->name, $group->snapshot_data[0]['acknowledged_by_name']);
    }
}
