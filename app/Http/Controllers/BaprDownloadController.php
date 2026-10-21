<?php

namespace App\Http\Controllers;

use App\Models\QualityRecordDestructionGroup;
use App\Services\QualityRecordDestructionExportService;
use Illuminate\Http\Request;

class BaprDownloadController extends Controller
{
    /**
     * Download the official Excel export for a specific BAPR group.
     */
    public function download(Request $request, int $id)
    {
        $user = $request->user();
        if (! $user || ! in_array($user->role, ['admin', 'manajemen'])) {
            abort(403, 'Anda tidak memiliki wewenang untuk mengunduh BAPR.');
        }

        $group = QualityRecordDestructionGroup::with('records')->findOrFail($id);

        if ($group->records->isEmpty() && empty($group->snapshot_data)) {
            abort(404, 'Grup BAPR tidak memiliki rekaman yang terkait.');
        }

        $service = new QualityRecordDestructionExportService();
        $result = $service->export($group);

        if ($result === false) {
            abort(500, 'File template 273.106.208 Berita Acara Pemusnahan Rekaman Rev00.xlsx tidak ditemukan.');
        }

        $filename = $service->getFilename($group);

        return response()->download($result, $filename)->deleteFileAfterSend();
    }
}
