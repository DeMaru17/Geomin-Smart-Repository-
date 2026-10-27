<?php

namespace App\Services\DocumentSync;

use App\Services\DocumentSync\DTO\ParsedFileInfo;
use App\Services\DocumentSync\DTO\ScannedFileInfo;

class FilenameParserService
{
    /**
     * Mem-parsing objek ScannedFileInfo menjadi ParsedFileInfo.
     */
    public function parseScannedFile(ScannedFileInfo $scanned): ParsedFileInfo
    {
        return $this->parse(
            filename: $scanned->filename,
            relativePath: $scanned->relativePath,
            sizeBytes: $scanned->sizeBytes,
            hash: $scanned->hash
        );
    }

    /**
     * Mengekstrak nomor dokumen, nomor revisi, dan petunjuk peran berkas dari nama file.
     */
    public function parse(string $filename, string $relativePath = '', int $sizeBytes = 0, string $hash = ''): ParsedFileInfo
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $basenameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);

        // 1. Ekstraksi Nomor Dokumen
        $extractedDocs = $this->extractDocumentNumbers($basenameWithoutExt);

        if (empty($extractedDocs)) {
            return new ParsedFileInfo(
                filename: $filename,
                relativePath: $relativePath,
                extension: $extension,
                sizeBytes: $sizeBytes,
                hash: $hash,
                documentNumber: null,
                revisionNumber: null,
                roleHint: null,
                parseStatus: 'NO_DOCUMENT_NUMBER',
                errorMessage: 'Nomor dokumen tidak ditemukan pada nama berkas.'
            );
        }

        // Cek jika terdapat lebih dari satu nomor dokumen yang BERBEDA (ambigu)
        $uniqueDocs = array_values(array_unique($extractedDocs));
        if (count($uniqueDocs) > 1) {
            return new ParsedFileInfo(
                filename: $filename,
                relativePath: $relativePath,
                extension: $extension,
                sizeBytes: $sizeBytes,
                hash: $hash,
                documentNumber: null,
                revisionNumber: null,
                roleHint: null,
                parseStatus: 'AMBIGUOUS_DOCUMENT',
                errorMessage: 'Ditemukan lebih dari satu kandidat nomor dokumen yang berbeda: ' . implode(', ', $uniqueDocs)
            );
        }

        $documentNumber = $uniqueDocs[0];

        // 2. Ekstraksi Nomor Revisi
        // Hilangkan bagian nomor dokumen agar angka dokumen tidak disalahartikan sebagai revisi
        $textForRevision = preg_replace('/(?<![a-zA-Z0-9])' . preg_quote($documentNumber, '/') . '(?![a-zA-Z0-9])/i', ' ', $basenameWithoutExt);
        // Hilangkan juga jika formatnya tanpa titik (slug)
        $slugDoc = str_replace('.', '', $documentNumber);
        $textForRevision = preg_replace('/(?<![a-zA-Z0-9])' . preg_quote($slugDoc, '/') . '(?![a-zA-Z0-9])/i', ' ', $textForRevision);

        $extractedRevs = $this->extractRevisionNumbers($textForRevision);

        $roleHint = $this->detectRoleHint($basenameWithoutExt);

        if (empty($extractedRevs)) {
            return new ParsedFileInfo(
                filename: $filename,
                relativePath: $relativePath,
                extension: $extension,
                sizeBytes: $sizeBytes,
                hash: $hash,
                documentNumber: $documentNumber,
                revisionNumber: null,
                roleHint: $roleHint,
                parseStatus: 'NO_REVISION',
                errorMessage: 'Nomor dokumen ditemukan, namun nomor revisi tidak terdeteksi.'
            );
        }

        // Cek jika terdapat lebih dari satu nomor revisi yang BERBEDA (ambigu)
        $uniqueRevs = array_values(array_unique($extractedRevs));
        if (count($uniqueRevs) > 1) {
            return new ParsedFileInfo(
                filename: $filename,
                relativePath: $relativePath,
                extension: $extension,
                sizeBytes: $sizeBytes,
                hash: $hash,
                documentNumber: $documentNumber,
                revisionNumber: null,
                roleHint: $roleHint,
                parseStatus: 'AMBIGUOUS_REVISION',
                errorMessage: 'Ditemukan lebih dari satu nomor revisi yang berbeda: ' . implode(', ', $uniqueRevs)
            );
        }

        $revisionNumber = $uniqueRevs[0];

        return new ParsedFileInfo(
            filename: $filename,
            relativePath: $relativePath,
            extension: $extension,
            sizeBytes: $sizeBytes,
            hash: $hash,
            documentNumber: $documentNumber,
            revisionNumber: $revisionNumber,
            roleHint: $roleHint,
            parseStatus: 'PARSED',
            errorMessage: null
        );
    }

    /**
     * Mengekstrak seluruh kandidat nomor dokumen dan menormalisasikannya.
     *
     * @return array<string>
     */
    private function extractDocumentNumbers(string $text): array
    {
        // 1. 273.xxx.yyy (IK & F standar)
        // 2. 273xxxyyy (slug seeder IK & F)
        // 3. 273.xxx (PK standar, pastikan tidak diikuti titik dan angka)
        // 4. 273xxx (slug seeder PK, pastikan tidak diikuti angka)
        // 5. Exxx (Dokumen Eksternal)
        $pattern = '/(?<![a-zA-Z0-9])(273\.\d{3}\.\d{3}|273\d{6}|273\.\d{3}(?!\.\d)|273\d{3}(?!\d)|[Ee]\d{3}(?!\d))(?![a-zA-Z0-9])/u';

        if (! preg_match_all($pattern, $text, $matches)) {
            return [];
        }

        $results = [];
        foreach ($matches[1] as $match) {
            $normalized = $this->normalizeDocumentNumber($match);
            if ($normalized !== null) {
                $results[] = $normalized;
            }
        }

        return $results;
    }

    /**
     * Menormalisasi string nomor dokumen ke bentuk kanonis GSR.
     */
    private function normalizeDocumentNumber(string $raw): ?string
    {
        $raw = trim($raw);

        // Kasus 1: Format slug 9 digit numerik (contoh: 273101101 -> 273.101.101)
        if (preg_match('/^273(\d{3})(\d{3})$/', $raw, $m)) {
            return "273.{$m[1]}.{$m[2]}";
        }

        // Kasus 2: Format slug 6 digit numerik (contoh: 273101 -> 273.101)
        if (preg_match('/^273(\d{3})$/', $raw, $m)) {
            return "273.{$m[1]}";
        }

        // Kasus 3: Format eksternal (contoh: e001 -> E001)
        if (preg_match('/^[Ee](\d{3})$/', $raw, $m)) {
            return 'E' . $m[1];
        }

        // Kasus 4: Format standar dengan titik (273.101 atau 273.101.101)
        if (preg_match('/^273\.\d{3}(?:\.\d{3})?$/', $raw)) {
            return $raw;
        }

        return null;
    }

    /**
     * Mengekstrak seluruh nomor revisi dari teks dan menormalisasi ke 2 digit zero-padded.
     *
     * @return array<string>
     */
    private function extractRevisionNumbers(string $text): array
    {
        // Pola penanda revisi: Rev, Rev., Revisi diikuti spasi/titik/strip/underscore dan angka 1-2 digit
        // Huruf tunggal 'R' sengaja tidak diizinkan agar tidak salah menginterpretasikan Ruang (R1), Reagen, atau kode parameter
        $pattern = '/(?:^|[\s_\-\.\(\[])(?:Rev(?:isi)?\.?)[_\s\-\.]*(\d{1,2})(?:$|[\s_\-\.\)\]])/iu';

        if (! preg_match_all($pattern, $text, $matches)) {
            return [];
        }

        $results = [];
        foreach ($matches[1] as $numStr) {
            $intVal = (int) $numStr;
            // Format 2 digit zero-padded ('00', '01', ..., '16')
            $results[] = str_pad((string) $intVal, 2, '0', STR_PAD_LEFT);
        }

        return $results;
    }

    /**
     * Mendeteksi indikasi peran berkas (khususnya untuk Formulir).
     */
    private function detectRoleHint(string $text): ?string
    {
        $isBlanko = preg_match('/(?:^|[\s_\-\.\(\[])(?:blanko|distribusi|template|form)(?:$|[\s_\-\.\)\]])/iu', $text);
        $isMaster = preg_match('/(?:^|[\s_\-\.\(\[])(?:master|asli|draft|editable)(?:$|[\s_\-\.\)\]])/iu', $text);

        if ($isBlanko && ! $isMaster) {
            return 'blanko';
        }

        if ($isMaster && ! $isBlanko) {
            return 'master';
        }

        // Jika keduanya ada atau tidak ada sama sekali, peran tidak dapat dipastikan otomatis
        return null;
    }
}
