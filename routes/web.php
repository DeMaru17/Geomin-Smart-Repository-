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

    return response()->file(Storage::path($revision->file_path));
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
