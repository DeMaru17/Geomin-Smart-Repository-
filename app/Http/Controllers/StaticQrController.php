<?php

namespace App\Http\Controllers;

use App\Models\DocumentRevision;

class StaticQrController extends Controller
{
    /**
     * Display validation detail page for a specific revision.
     */
    public function __invoke(string $qrToken)
    {
        $revision = DocumentRevision::where('qr_token', $qrToken)->first();

        if (! $revision) {
            abort(404);
        }

        $revision->load('document');

        // Map revision status to validation display status
        $status = match (true) {
            in_array($revision->status, ['Published', 'Terbit']) => 'Valid',
            $revision->status === 'Obsolete' => 'Obsolete',
            default => 'Belum Terbit',
        };

        $showViewerButton = in_array($revision->status, ['Published', 'Terbit']);

        return view('validasi-cetak', [
            'document_title' => $revision->document->title,
            'revision_number' => $revision->revision_number,
            'status' => $status,
            'published_date' => $revision->created_at->format('d M Y'),
            'revision_id' => $revision->id,
            'show_viewer_button' => $showViewerButton,
        ]);
    }
}
