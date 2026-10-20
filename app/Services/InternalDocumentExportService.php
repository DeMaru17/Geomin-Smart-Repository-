<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentRevision;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InternalDocumentExportService
{
    /**
     * Definisi urutan dan label kategori resmi sesuai template Form 273.106.202.
     */
    protected array $categoryDefinitions = [
        'PK' => [
            'code' => 'PK',
            'title' => 'PROSEDUR KERJA',
            'match_names' => ['prosedur kerja', 'prosedur'],
        ],
        'IK' => [
            'code' => 'IK',
            'title' => 'INTRUKSI KERJA',
            'match_names' => ['instruksi kerja', 'intruksi kerja'],
        ],
        'F' => [
            'code' => 'F',
            'title' => 'FORMULIR',
            'match_names' => ['form', 'formulir', 'formulir/form'],
        ],
    ];

    /**
     * Path ke template Excel resmi.
     */
    public function getTemplatePath(): string
    {
        return storage_path('app/templates/273.106.202 Daftar Induk Dokumen Rev 04.xlsx');
    }

    /**
     * Nama file hasil ekspor.
     */
    public function getFilename(): string
    {
        return 'Form-273.106.202-Daftar-Induk-Dokumen.xlsx';
    }

    /**
     * Query dasar untuk mengambil dokumen internal yang memenuhi syarat.
     * Hanya dokumen internal, status aktif (bukan Obsolete), dan memiliki relasi revisi terbit.
     */
    public function getEligibleDocumentsQuery(): Builder
    {
        return Document::query()
            ->where('is_external', false)
            ->where(function (Builder $query) {
                $query->whereNull('status')
                    ->orWhere('status', '!=', 'Obsolete');
            })
            ->whereHas('revisions', function (Builder $query) {
                $query->whereIn('status', ['Published', 'Terbit']);
            })
            ->with(['category', 'revisions']);
    }

    /**
     * Ambil revisi terbit yang saat ini berlaku untuk setiap dokumen.
     * Memilih status 'Published' atau 'Terbit', diurutkan berdasarkan tanggal revisi terbaru dan ID.
     */
    public function getValidPublishedRevision(Document $document): ?DocumentRevision
    {
        return $document->revisions
            ->filter(fn (DocumentRevision $rev) => in_array($rev->status, ['Published', 'Terbit'], true))
            ->sortByDesc(fn (DocumentRevision $rev) => [
                $rev->revision_date?->timestamp ?? 0,
                $rev->id,
            ])
            ->first();
    }

    /**
     * Kelompokkan dan urutkan dokumen yang memenuhi syarat:
     * 1. Kategori diurutkan tetap: Prosedur Kerja -> Instruksi Kerja -> Formulir/Form
     * 2. Di dalam kategori, nomor dokumen diurutkan secara natural.
     * 3. Kategori tanpa dokumen aktif tidak ditampilkan.
     */
    public function getDocumentsGroupedByCategory(?Collection $documents = null): array
    {
        $documents ??= $this->getEligibleDocumentsQuery()->get();

        $groups = [];
        foreach ($this->categoryDefinitions as $key => $def) {
            $groups[$key] = [
                'title' => $def['title'],
                'documents' => collect(),
            ];
        }

        foreach ($documents as $document) {
            // Pastikan dokumen aktif dan memiliki revisi terbit yang valid
            if ($document->is_external || $document->status === 'Obsolete') {
                continue;
            }

            if (! $this->getValidPublishedRevision($document)) {
                continue;
            }

            $catKey = $this->resolveCategoryKey($document);
            if ($catKey !== null && isset($groups[$catKey])) {
                $groups[$catKey]['documents']->push($document);
            }
        }

        $result = [];
        foreach ($groups as $key => $group) {
            if ($group['documents']->isEmpty()) {
                continue;
            }

            // Natural sort berdasarkan nomor dokumen
            $sorted = $group['documents']->sort(function (Document $a, Document $b) {
                return strnatcasecmp((string) $a->document_number, (string) $b->document_number);
            })->values();

            $result[$key] = [
                'title' => $group['title'],
                'documents' => $sorted,
            ];
        }

        return $result;
    }

    /**
     * Identifikasi kategori dokumen ke salah satu dari 3 grup (PK, IK, F).
     */
    public function resolveCategoryKey(Document $document): ?string
    {
        $category = $document->category;
        if (! $category) {
            return null;
        }

        $code = strtoupper(trim((string) $category->code));
        foreach ($this->categoryDefinitions as $key => $def) {
            if ($code === $def['code']) {
                return $key;
            }
        }

        $name = mb_strtolower(trim((string) $category->name));
        foreach ($this->categoryDefinitions as $key => $def) {
            foreach ($def['match_names'] as $match) {
                if (str_contains($name, $match)) {
                    return $key;
                }
            }
        }

        return null;
    }

    /**
     * Muat template Excel, isi data dokumen secara dinamis, dan kembalikan Spreadsheet object.
     * Mengembalikan false jika template tidak ditemukan atau tidak ada dokumen yang memenuhi syarat.
     */
    public function generateSpreadsheet(?Collection $documents = null): Spreadsheet|false
    {
        $templatePath = $this->getTemplatePath();
        if (! file_exists($templatePath)) {
            return false;
        }

        $groupedData = $this->getDocumentsGroupedByCategory($documents);
        if (empty($groupedData)) {
            return false;
        }

        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $spreadsheet->getActiveSheet();

        // Template awal memiliki 9 baris placeholder (baris 8 s/d 16)
        // Kita hapus baris placeholder ini. Blok tanda tangan dan gambarnya akan bergeser ke atas secara otomatis.
        $sheet->removeRow(8, 9);

        $thinBorderArray = [
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_THIN],
                'bottom' => ['borderStyle' => Border::BORDER_THIN],
                'left' => ['borderStyle' => Border::BORDER_THIN],
                'right' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ];

        $currentRow = 8;
        $nomor = 1;

        foreach ($groupedData as $group) {
            $catTitle = $group['title'];
            $docs = $group['documents'];

            // 1. Baris Kategori
            $sheet->insertNewRowBefore($currentRow, 1);
            $sheet->mergeCells("A{$currentRow}:G{$currentRow}");
            $sheet->setCellValue("A{$currentRow}", $catTitle);
            $sheet->getStyle("A{$currentRow}")->getFont()->setName('Cambria')->setSize(10)->setBold(true);
            $sheet->getStyle("A{$currentRow}")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("A{$currentRow}:G{$currentRow}")->applyFromArray($thinBorderArray);
            $sheet->getRowDimension($currentRow)
                ->setRowHeight(18)
                ->setVisible(true)
                ->setZeroHeight(false)
                ->setCollapsed(false);
            $currentRow++;

            // 2. Baris Dokumen (penomoran berkesinambungan global dari PK -> IK -> F)
            foreach ($docs as $doc) {
                $revision = $this->getValidPublishedRevision($doc);
                $revisionNumber = (string) ($revision?->revision_number ?? '00');
                if (is_numeric($revisionNumber)) {
                    $revisionNumber = str_pad($revisionNumber, 2, '0', STR_PAD_LEFT);
                }

                $revisionDate = '';
                if ($revision?->revision_date) {
                    $revisionDate = $revision->revision_date instanceof \DateTimeInterface
                        ? $revision->revision_date->format('d/m/Y')
                        : date('d/m/Y', strtotime((string) $revision->revision_date));
                }

                $sheet->insertNewRowBefore($currentRow, 1);

                $sheet->setCellValue("A{$currentRow}", $nomor);
                $sheet->setCellValueExplicit("B{$currentRow}", (string) $doc->document_number, DataType::TYPE_STRING);
                $sheet->setCellValue("C{$currentRow}", (string) $doc->title);
                $sheet->setCellValue("D{$currentRow}", (string) ($doc->category?->name ?? ''));
                $sheet->setCellValueExplicit("E{$currentRow}", $revisionNumber, DataType::TYPE_STRING);
                $sheet->setCellValue("F{$currentRow}", $revisionDate);
                $sheet->setCellValue("G{$currentRow}", 'sampai revisi berikutnya');

                // Fonts
                foreach (['A', 'B', 'C', 'D', 'G'] as $col) {
                    $sheet->getStyle("{$col}{$currentRow}")->getFont()->setName('Cambria')->setSize(10);
                }
                foreach (['E', 'F'] as $col) {
                    $sheet->getStyle("{$col}{$currentRow}")->getFont()->setName('Calibri')->setSize(10);
                }

                // Alignments
                $sheet->getStyle("A{$currentRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("B{$currentRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("C{$currentRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);
                $sheet->getStyle("D{$currentRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("E{$currentRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("F{$currentRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("G{$currentRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // Borders & Height & Visibility
                $sheet->getStyle("A{$currentRow}:G{$currentRow}")->applyFromArray($thinBorderArray);
                $sheet->getRowDimension($currentRow)
                    ->setRowHeight(18)
                    ->setVisible(true)
                    ->setZeroHeight(false)
                    ->setCollapsed(false);

                $currentRow++;
                $nomor++;
            }

            // 3. Baris Kosong Pemisah Kategori
            $sheet->insertNewRowBefore($currentRow, 1);
            $sheet->getStyle("A{$currentRow}:G{$currentRow}")->applyFromArray($thinBorderArray);
            $sheet->getRowDimension($currentRow)
                ->setRowHeight(13.8)
                ->setVisible(true)
                ->setZeroHeight(false)
                ->setCollapsed(false);
            $currentRow++;
        }

        $lastTableDataRow = $currentRow - 1;

        // Sesuaikan AutoFilter agar mencakup header tabel (A7) hingga akhir baris data ($lastTableDataRow)
        $autoFilter = $sheet->getAutoFilter();
        // Bersihkan filterColumn template yang menyebabkan showHideRows menyembunyikan baris data
        foreach (array_keys($autoFilter->getColumns()) as $colKey) {
            try {
                $autoFilter->clearColumn($colKey);
            } catch (\Throwable) {
            }
        }
        $refl = new \ReflectionProperty($autoFilter, 'columns');
        $refl->setValue($autoFilter, []);

        $autoFilter->setRange("A7:G{$lastTableDataRow}");

        // Pastikan seluruh baris di worksheet terlihat (visible = true, zeroHeight = false, collapsed = false)
        foreach ($sheet->getRowDimensions() as $rowDimension) {
            $rowDimension->setVisible(true);
            $rowDimension->setZeroHeight(false);
            $rowDimension->setCollapsed(false);
        }

        return $spreadsheet;
    }

    /**
     * Ekspor Daftar Induk Dokumen Internal ke StreamedResponse untuk diunduh langsung via browser.
     * Mengembalikan false jika template tidak tersedia atau data tidak ditemukan.
     */
    public function export(?Collection $documents = null): StreamedResponse|false
    {
        $spreadsheet = $this->generateSpreadsheet($documents);
        if ($spreadsheet === false) {
            return false;
        }

        $filename = $this->getFilename();

        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }
}
