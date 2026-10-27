<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Direktori Sumber Berkas Impor (Server Folder)
    |--------------------------------------------------------------------------
    | Lokasi default tempat berkas ditempatkan via SFTP/File Manager.
    | Berada di dalam storage/app/private/ agar terlindung dari akses web publik.
    */
    'source_path' => env('DOCUMENT_SYNC_SOURCE_PATH', storage_path('app/private/document-import')),

    /*
    |--------------------------------------------------------------------------
    | Batas Kuota Pemindaian dan Ukuran Berkas
    |--------------------------------------------------------------------------
    | Batasan untuk mencegah resource exhaustion dan memory limit timeout.
    */
    'max_files_per_batch' => (int) env('DOCUMENT_SYNC_MAX_FILES', 500),
    'max_file_size_mb' => (int) env('DOCUMENT_SYNC_MAX_FILE_SIZE_MB', 100),
    'max_total_batch_size_mb' => (int) env('DOCUMENT_SYNC_MAX_BATCH_SIZE_MB', 1024), // 1 GB

    /*
    |--------------------------------------------------------------------------
    | Ekstensi Berkas yang Diizinkan
    |--------------------------------------------------------------------------
    */
    'allowed_extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx'],

    /*
    |--------------------------------------------------------------------------
    | Konvensi Penyimpanan Target Berkas
    |--------------------------------------------------------------------------
    | Mengikuti konvensi penyimpanan Filament yang sudah ada pada aplikasi.
    */
    'disk' => env('DOCUMENT_SYNC_DISK', 'local'),
    'pdf_directory' => env('DOCUMENT_SYNC_PDF_DIR', 'geomin-documents'),
    'word_directory' => env('DOCUMENT_SYNC_WORD_DIR', 'geomin-documents/word'),
];
