<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validasi Cetak - {{ $document_title }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">

    <div class="bg-white rounded-2xl shadow-lg max-w-md w-full p-8">
        {{-- Header --}}
        <div class="text-center mb-6">
            <svg class="w-12 h-12 mx-auto mb-3 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.956 11.956 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
            <h1 class="text-xl font-bold text-gray-800">Validasi Salinan Cetak</h1>
        </div>

        {{-- Document Title --}}
        <div class="mb-4">
            <p class="text-sm text-gray-500">Judul Dokumen</p>
            <p class="text-lg font-semibold text-gray-900">{{ $document_title }}</p>
        </div>

        {{-- Revision Number --}}
        <div class="mb-4">
            <p class="text-sm text-gray-500">Nomor Revisi</p>
            <p class="text-base font-medium text-gray-800">Rev. {{ $revision_number }}</p>
        </div>

        {{-- Validation Status --}}
        <div class="mb-4">
            <p class="text-sm text-gray-500 mb-1">Status Validasi</p>
            @if ($status === 'Valid')
                <span
                    class="inline-flex items-center px-4 py-2 rounded-full text-sm font-bold bg-green-100 text-green-800 border border-green-300">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Valid
                </span>
            @elseif ($status === 'Obsolete')
                <span
                    class="inline-flex items-center px-4 py-2 rounded-full text-sm font-bold bg-red-100 text-red-800 border border-red-300">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    Obsolete
                </span>
            @else
                <span
                    class="inline-flex items-center px-4 py-2 rounded-full text-sm font-bold bg-gray-100 text-gray-600 border border-gray-300">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Belum Terbit
                </span>
            @endif
        </div>

        {{-- Published Date --}}
        <div class="mb-6">
            <p class="text-sm text-gray-500">Tanggal Terbit</p>
            <p class="text-base font-medium text-gray-800">{{ $published_date }}</p>
        </div>

        {{-- Buka Dokumen Button (only shown for Published/Terbit statuses)
        @if ($show_viewer_button)
            <a href="{{ route('secure.viewer', ['id' => $revision_id]) }}"
                class="block w-full text-center bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-4 rounded-lg transition duration-200">
                Buka Dokumen
            </a>
        @endif --}}

        {{-- Footer --}}
        <p class="text-xs text-gray-400 text-center mt-6">
            Halaman ini dihasilkan oleh sistem Geomin Smart Repository untuk memverifikasi keaslian salinan cetak
            dokumen.
        </p>
    </div>

</body>

</html>
