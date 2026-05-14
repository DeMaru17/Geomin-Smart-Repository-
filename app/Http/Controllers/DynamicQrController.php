<?php

namespace App\Http\Controllers;

use App\Models\Document;

class DynamicQrController extends Controller
{
    /**
     * Redirect to the latest published revision's secure viewer.
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

        return redirect()->route('secure.viewer', ['id' => $revision->id]);
    }
}
