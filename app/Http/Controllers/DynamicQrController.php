<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentDistributionLog;
use Illuminate\Support\Facades\Auth;

class DynamicQrController extends Controller
{
    /**
     * Redirect to the latest published revision's secure viewer.
     * Logs distribution access for authenticated users (Skenario A).
     */
    public function __invoke(string $documentNumber)
    {
        $document = Document::where('document_number', $documentNumber)->first();

        if (! $document) {
            abort(404);
        }

        $revision = $document->revisions()
            ->where('status', 'Published')
            ->orderByDesc('id')
            ->first();

        if (! $revision) {
            return view('no-published-revision', ['document' => $document]);
        }

        // Catat log distribusi digital via QR Dinamis (Skenario A)
        DocumentDistributionLog::create([
            'user_id' => Auth::id(),
            'recipient_name' => Auth::user()->name,
            'purpose' => 'Akses Instruksi Kerja di instrumen',
            'action' => 'Akses Digital (QR Instrumen/Alat)',
            'is_qr_access' => true,
            'document_revision_id' => $revision->id,
            'accessed_at' => now(),
        ]);

        return redirect()->route('secure.viewer', ['id' => $revision->id]);
    }
}
