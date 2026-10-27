<?php

namespace App\Services\DocumentSync;

use App\Models\Document;
use App\Models\DocumentRevision;
use App\Services\DocumentSync\DTO\MatchResult;
use App\Services\DocumentSync\DTO\ParsedFileInfo;

class DocumentMatcherService
{
    public function __construct(
        protected PlaceholderDetectorService $placeholderDetector
    ) {}

    /**
     * Mencocokkan sekumpulan ParsedFileInfo (batch) dan mendeteksi konflik duplikasi target.
     *
     * @param array<ParsedFileInfo> $parsedFiles
     * @return array<MatchResult>
     */
    public function matchBatch(array $parsedFiles): array
    {
        $results = [];
        $slotGroups = [];

        // 1. Lakukan pencocokan individual untuk setiap berkas
        foreach ($parsedFiles as $index => $parsedInfo) {
            $matchResult = $this->matchSingle($parsedInfo);
            $results[$index] = $matchResult;

            // Kelompokkan target yang valid untuk deteksi konflik
            if ($matchResult->documentId !== null && $matchResult->revisionId !== null && $matchResult->targetSlot !== null) {
                $targetKey = "{$matchResult->documentId}_{$matchResult->revisionId}_{$matchResult->targetSlot}";
                $slotGroups[$targetKey][] = $index;
            }
        }

        // 2. Deteksi konflik: jika >1 berkas menargetkan slot dokumen yang sama persis
        foreach ($slotGroups as $targetKey => $indices) {
            if (count($indices) > 1) {
                $duplicateCount = count($indices);
                foreach ($indices as $idx) {
                    $original = $results[$idx];
                    $results[$idx] = new MatchResult(
                        parsedInfo: $original->parsedInfo,
                        documentId: $original->documentId,
                        revisionId: $original->revisionId,
                        documentNumber: $original->documentNumber,
                        revisionNumber: $original->revisionNumber,
                        categoryCode: $original->categoryCode,
                        targetSlot: $original->targetSlot,
                        matchStatus: 'CONFLICT_DUPLICATE_SOURCE',
                        slotCurrentStatus: $original->slotCurrentStatus,
                        decision: 'CONFLICT_DUPLICATE_SOURCE',
                        errorMessage: "Konflik: Terdapat {$duplicateCount} berkas sumber yang menargetkan dokumen, revisi, dan slot yang sama."
                    );
                }
            }
        }

        return $results;
    }

    /**
     * Mencocokkan satu berkas ParsedFileInfo ke katalog dokumen & revisi GSR.
     */
    public function matchSingle(ParsedFileInfo $parsedInfo): MatchResult
    {
        // 1. Validasi keberadaan nomor dokumen dari hasil parsing
        if (empty($parsedInfo->documentNumber)) {
            return new MatchResult(
                parsedInfo: $parsedInfo,
                matchStatus: 'DOC_NOT_FOUND',
                slotCurrentStatus: 'UNKNOWN',
                decision: 'UNMATCHED_DOCUMENT',
                errorMessage: $parsedInfo->errorMessage ?: 'Nomor dokumen tidak terdeteksi pada nama berkas.'
            );
        }

        // 2. Cari dokumen di database (eager load revisi dan kategori)
        $matchingDocs = Document::with(['revisions', 'category'])
            ->where('document_number', $parsedInfo->documentNumber)
            ->get();

        if ($matchingDocs->count() > 1) {
            return new MatchResult(
                parsedInfo: $parsedInfo,
                documentNumber: $parsedInfo->documentNumber,
                matchStatus: 'AMBIGUOUS_DOCUMENT',
                slotCurrentStatus: 'UNKNOWN',
                decision: 'AMBIGUOUS_MATCH',
                errorMessage: "Ditemukan {$matchingDocs->count()} dokumen dengan nomor [{$parsedInfo->documentNumber}] di sistem. Memerlukan penanganan manual."
            );
        }

        $document = $matchingDocs->first();

        if (! $document) {
            return new MatchResult(
                parsedInfo: $parsedInfo,
                documentNumber: $parsedInfo->documentNumber,
                matchStatus: 'DOC_NOT_FOUND',
                slotCurrentStatus: 'UNKNOWN',
                decision: 'UNMATCHED_DOCUMENT',
                errorMessage: "Dokumen dengan nomor [{$parsedInfo->documentNumber}] tidak ditemukan di sistem."
            );
        }

        $categoryCode = $document->category?->code ?? ($document->is_external ? 'EXTERNAL' : null);

        // 3. Validasi nomor revisi
        if (empty($parsedInfo->revisionNumber)) {
            return new MatchResult(
                parsedInfo: $parsedInfo,
                documentId: $document->id,
                documentNumber: $document->document_number,
                categoryCode: $categoryCode,
                matchStatus: 'AMBIGUOUS_REVISION',
                slotCurrentStatus: 'UNKNOWN',
                decision: 'AMBIGUOUS_MATCH',
                errorMessage: 'Nomor dokumen ditemukan, namun nomor revisi tidak terdeteksi pada nama berkas.'
            );
        }

        // 4. Cari revisi yang cocok pada dokumen (mendukung format zero-padded string maupun integer)
        $targetRevStr = (string) $parsedInfo->revisionNumber;
        $targetRevInt = is_numeric($targetRevStr) ? (int) $targetRevStr : null;

        $matchingRevisions = $document->revisions->filter(function ($rev) use ($targetRevStr, $targetRevInt) {
            $dbRev = (string) $rev->revision_number;
            if ($dbRev === $targetRevStr) {
                return true;
            }
            if ($targetRevInt !== null && is_numeric($dbRev) && (int) $dbRev === $targetRevInt) {
                return true;
            }

            return false;
        });

        if ($matchingRevisions->count() > 1) {
            return new MatchResult(
                parsedInfo: $parsedInfo,
                documentId: $document->id,
                documentNumber: $document->document_number,
                revisionNumber: $parsedInfo->revisionNumber,
                categoryCode: $categoryCode,
                matchStatus: 'AMBIGUOUS_REVISION',
                slotCurrentStatus: 'UNKNOWN',
                decision: 'AMBIGUOUS_MATCH',
                errorMessage: "Terdapat {$matchingRevisions->count()} revisi yang sesuai dengan nomor [{$parsedInfo->revisionNumber}] pada dokumen ini. Memerlukan penanganan manual."
            );
        }

        $revision = $matchingRevisions->first();

        if (! $revision) {
            return new MatchResult(
                parsedInfo: $parsedInfo,
                documentId: $document->id,
                documentNumber: $document->document_number,
                revisionNumber: $parsedInfo->revisionNumber,
                categoryCode: $categoryCode,
                matchStatus: 'REV_NOT_FOUND',
                slotCurrentStatus: 'UNKNOWN',
                decision: 'MANUAL_REVIEW',
                errorMessage: "Revisi [{$parsedInfo->revisionNumber}] tidak terdaftar pada Dokumen [{$document->document_number}]."
            );
        }

        // 5. Evaluasi Kategori Dokumen dan Tentukan Target Slot
        $slotResolution = $this->resolveTargetSlot($parsedInfo, $document);

        if ($slotResolution['status'] !== 'VALID') {
            return new MatchResult(
                parsedInfo: $parsedInfo,
                documentId: $document->id,
                revisionId: $revision->id,
                documentNumber: $document->document_number,
                revisionNumber: $revision->revision_number,
                categoryCode: $categoryCode,
                targetSlot: $slotResolution['slot'] ?? null,
                matchStatus: $slotResolution['status'],
                slotCurrentStatus: 'UNKNOWN',
                decision: $slotResolution['decision'],
                errorMessage: $slotResolution['message']
            );
        }

        $targetSlot = $slotResolution['slot'];

        // 6. Evaluasi Status Ketersediaan Slot menggunakan PlaceholderDetectorService
        $slotStatus = $this->placeholderDetector->detectForRevision($revision, $targetSlot);

        $decision = match ($slotStatus) {
            PlaceholderDetectorService::STATUS_EMPTY => 'READY_TO_ATTACH',
            PlaceholderDetectorService::STATUS_PHYSICAL_EXISTS => 'SLOT_OCCUPIED',
            PlaceholderDetectorService::STATUS_PLACEHOLDER_INTERNAL,
            PlaceholderDetectorService::STATUS_PLACEHOLDER_EXTERNAL => 'PLACEHOLDER_REPLACEABLE',
            PlaceholderDetectorService::STATUS_BROKEN_REFERENCE => 'MANUAL_REVIEW',
            PlaceholderDetectorService::STATUS_STORAGE_UNREACHABLE => 'MANUAL_REVIEW',
            default => 'MANUAL_REVIEW',
        };

        $errorMessage = match ($slotStatus) {
            PlaceholderDetectorService::STATUS_PHYSICAL_EXISTS => 'Slot ini sudah terisi berkas fisik valid pada storage (dilarang menimpa).',
            PlaceholderDetectorService::STATUS_BROKEN_REFERENCE => 'Path tercatat di database namun berkas fisik tidak ditemukan dan bukan placeholder seeder resmi.',
            PlaceholderDetectorService::STATUS_STORAGE_UNREACHABLE => 'Media penyimpanan storage tidak dapat diakses saat verifikasi berkas.',
            default => null,
        };

        return new MatchResult(
            parsedInfo: $parsedInfo,
            documentId: $document->id,
            revisionId: $revision->id,
            documentNumber: $document->document_number,
            revisionNumber: $revision->revision_number,
            categoryCode: $categoryCode,
            targetSlot: $targetSlot,
            matchStatus: 'MATCHED',
            slotCurrentStatus: $slotStatus,
            decision: $decision,
            errorMessage: $errorMessage
        );
    }

    /**
     * Menentukan slot target (file_path atau word_file_path) berdasarkan kategori dan ekstensi berkas.
     *
     * @return array{status: string, decision: string, slot: ?string, message: ?string}
     */
    private function resolveTargetSlot(ParsedFileInfo $parsedInfo, Document $document): array
    {
        $ext = strtolower($parsedInfo->extension);
        $categoryCode = $document->category?->code;
        $isExternal = (bool) $document->is_external;

        // Kategori Dokumen Eksternal
        if ($isExternal || $categoryCode === 'EXTERNAL') {
            if ($ext === 'pdf') {
                return ['status' => 'VALID', 'decision' => 'VALID', 'slot' => 'file_path', 'message' => null];
            }

            return [
                'status' => 'INVALID_EXTENSION',
                'decision' => 'INVALID_EXTENSION',
                'slot' => null,
                'message' => "Dokumen Eksternal hanya menerima berkas PDF. Ekstensi [{$ext}] tidak diizinkan.",
            ];
        }

        // Kategori Formulir ('F')
        if ($categoryCode === 'F') {
            // Larangan mutlak: PDF tidak boleh otomatis dipasang sebagai blanko Formulir F
            if ($ext === 'pdf') {
                return [
                    'status' => 'INVALID_EXTENSION',
                    'decision' => 'INVALID_EXTENSION',
                    'slot' => null,
                    'message' => 'Berkas PDF tidak diizinkan untuk kategori Formulir. Formulir menggunakan Blanko Excel (.xlsx/.xls).',
                ];
            }

            // Word master formulir
            if (in_array($ext, ['doc', 'docx'], true)) {
                return ['status' => 'VALID', 'decision' => 'VALID', 'slot' => 'word_file_path', 'message' => null];
            }

            // Spreadsheet Excel untuk Formulir: secara aturan wajib masuk ke file_path (Blanko Distribusi)
            if (in_array($ext, ['xls', 'xlsx'], true)) {
                if ($parsedInfo->roleHint === 'master') {
                    return ['status' => 'VALID', 'decision' => 'VALID', 'slot' => 'word_file_path', 'message' => null];
                }

                return ['status' => 'VALID', 'decision' => 'VALID', 'slot' => 'file_path', 'message' => null];
            }

            return [
                'status' => 'INVALID_EXTENSION',
                'decision' => 'INVALID_EXTENSION',
                'slot' => null,
                'message' => "Ekstensi [{$ext}] tidak didukung untuk kategori Formulir.",
            ];
        }

        // Kategori Prosedur Kerja (PK) dan Instruksi Kerja (IK)
        if (in_array($categoryCode, ['PK', 'IK'], true)) {
            if ($ext === 'pdf') {
                return ['status' => 'VALID', 'decision' => 'VALID', 'slot' => 'file_path', 'message' => null];
            }

            if (in_array($ext, ['doc', 'docx'], true)) {
                return ['status' => 'VALID', 'decision' => 'VALID', 'slot' => 'word_file_path', 'message' => null];
            }

            return [
                'status' => 'INVALID_EXTENSION',
                'decision' => 'INVALID_EXTENSION',
                'slot' => null,
                'message' => "Kategori [{$categoryCode}] hanya menerima PDF untuk dokumen resmi atau Word untuk dokumen master. Ekstensi [{$ext}] ditolak.",
            ];
        }

        // Kategori Panduan Mutu (PM) belum memiliki bukti penggunaan dokumen aktif di GSR, diarahkan ke peninjauan manual
        if ($categoryCode === 'PM') {
            return [
                'status' => 'MANUAL_REVIEW',
                'decision' => 'MANUAL_REVIEW',
                'slot' => null,
                'message' => 'Kategori Panduan Mutu (PM) belum memiliki konvensi pemasangan terbukti dalam sistem dan memerlukan peninjauan manual.',
            ];
        }

        // Default jika kategori tidak dikenal
        return [
            'status' => 'MANUAL_REVIEW',
            'decision' => 'MANUAL_REVIEW',
            'slot' => null,
            'message' => "Kategori dokumen [{$categoryCode}] belum didukung untuk pencocokan otomatis.",
        ];
    }
}
