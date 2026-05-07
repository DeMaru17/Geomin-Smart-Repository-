<?php

use App\Models\DocumentRevision;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// 1. Route untuk mengalirkan file PDF (digunakan oleh PDF.js di latar belakang)
Route::get('/pdf-stream/{id}', function ($id) {
    $revision = DocumentRevision::findOrFail($id);
    if (! auth()->check()) {
        abort(403);
    }

    return response()->file(Storage::path($revision->file_path));
})->name('pdf.stream')->middleware(['auth']);

// 2. Route untuk menampilkan Halaman Antarmuka Figma (UI Layar Penuh)
Route::get('/secure-viewer/{id}', function ($id) {
    $revision = DocumentRevision::with('document')->findOrFail($id);
    if (! auth()->check()) {
        abort(403);
    }

    // Ini yang mengirim variabel $revision ke fail secure-viewer.blade.php
    return view('secure-viewer', ['revision' => $revision]);
})->name('secure.viewer')->middleware(['auth']);
