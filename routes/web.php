<?php

use App\Models\DocumentRevision;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;

Route::get('/pdf-viewer/{id}', function ($id) {
    $revision = DocumentRevision::findOrFail($id);

    if (! auth()->check()) {
        abort(403);
    }

    $path = Storage::path($revision->file_path);

    return Response::make(file_get_contents($path), 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'inline; filename="'.$revision->file_path.'"',
    ]);
})->name('pdf.view')->middleware(['auth']);
