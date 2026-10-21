<?php

use App\Http\Controllers\DynamicQrController;
use App\Http\Controllers\StaticQrController;
use App\Models\DocumentRevision;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;


Route::get('/pdf-stream/{id}', function ($id) {
    $revision = DocumentRevision::findOrFail($id);
    if (! auth()->check()) {
        abort(403);
    }

    \Illuminate\Support\Facades\Log::info("pdf-stream called for revision #{$id}, status: {$revision->status}");

    // ETag based on revision id + status + updated_at — changes when status changes
    $etag = md5($revision->id . $revision->status . $revision->updated_at);
    $requestEtag = request()->header('If-None-Match');

    if ($requestEtag && $requestEtag === '"' . $etag . '"') {
        return response('', 304);
    }

    $cacheHeaders = [
        'ETag' => '"' . $etag . '"',
        'Cache-Control' => 'private, max-age=86400, must-revalidate',
    ];

    // Obsolete revisions get the OBSOLETE stamp
    if ($revision->status === 'Obsolete') {
        try {
            $pdfContent = app(\App\Services\PdfStamperService::class)->stampObsolete($revision);

            return response($pdfContent, 200, array_merge($cacheHeaders, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline',
            ]));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Obsolete stamp failed: ' . $e->getMessage());
            return response()->file(Storage::path($revision->file_path), $cacheHeaders);
        }
    }

    return response()->file(Storage::path($revision->file_path), $cacheHeaders);
})->name('pdf.stream')->middleware(['auth']);


Route::get('/secure-viewer/{id}', function ($id) {
    $revision = DocumentRevision::with('document')->findOrFail($id);
    if (! auth()->check()) {
        abort(403);
    }


    return view('secure-viewer', ['revision' => $revision]);
})->name('secure.viewer')->middleware(['auth']);


Route::get('/dokumen/aktif/{document_number}', DynamicQrController::class)
    ->name('dokumen.aktif')
    ->middleware(['auth']);

Route::get('/validasi-cetak/{qr_token}', StaticQrController::class)
    ->name('validasi.cetak');

Route::get('/masuk-sistem', function () {
    return redirect(filament()->getLoginUrl());
})->name('login');


Route::get('/quality-record-stream/{id}', function ($id) {
    $record = \App\Models\QualityRecord::findOrFail($id);
    if (! auth()->check()) {
        abort(403);
    }

    $filePath = Storage::disk('local')->path($record->file_path);

    if (! file_exists($filePath)) {
        abort(404);
    }

    // Destroyed PDF records get the OBSOLETE stamp
    if ($record->status === 'Dimusnahkan' && strtolower(pathinfo($record->file_path, PATHINFO_EXTENSION)) === 'pdf') {
        $pdfContent = app(\App\Services\PdfStamperService::class)->stampObsoleteFromPath($record->file_path);

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    return response()->file($filePath);
})->name('quality-record.stream')->middleware(['auth']);


Route::get('/quality-record-viewer/{id}', function ($id) {
    $record = \App\Models\QualityRecord::with('document')->findOrFail($id);
    if (! auth()->check()) {
        abort(403);
    }

    return view('quality-record-viewer', ['record' => $record]);
})->name('quality-record.viewer')->middleware(['auth']);

Route::get('/bapd/download/{id}', [\App\Http\Controllers\BapdDownloadController::class, 'download'])
    ->name('bapd.download')
    ->middleware(['auth']);

Route::get('/bapr/download/{id}', [\App\Http\Controllers\BaprDownloadController::class, 'download'])
    ->name('bapr.download')
    ->middleware(['auth']);
