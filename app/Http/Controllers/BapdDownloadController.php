<?php

namespace App\Http\Controllers;

use App\Models\DocumentDestructionGroup;
use App\Services\DocumentDestructionExportService;
use Illuminate\Http\Request;

class BapdDownloadController extends Controller
{
    /**
     * Download the official Excel export for a specific BAPD group.
     */
    public function download(Request $request, int $id)
    {
        $user = $request->user();
        if (! $user || ! in_array($user->role, ['admin', 'manajemen'])) {
            abort(403, 'Anda tidak memiliki wewenang untuk mengunduh BAPD.');
        }

        $group = DocumentDestructionGroup::with('destructions')->findOrFail($id);

        if ($group->destructions->isEmpty() && empty($group->snapshot_data)) {
            abort(404, 'Grup BAPD tidak memiliki dokumen yang terkait.');
        }

        $service = new DocumentDestructionExportService();
        $result = $service->export($group);

        if ($result === false) {
            abort(500, 'File template 273.106.207 Berita Acara Pemusnahan Dokumen.xlsx tidak ditemukan.');
        }

        $filename = $service->getFilename($group);

        return response()->download($result, $filename)->deleteFileAfterSend();
    }
}
