<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Belum Ada Versi Terbit - {{ $document->title }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4">

    <div class="bg-white rounded-2xl shadow-lg max-w-md w-full p-8 text-center">
        {{-- Icon --}}
        <div class="mx-auto w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mb-6">
            <svg class="w-8 h-8 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                </path>
            </svg>
        </div>

        {{-- Document title --}}
        <h1 class="text-xl font-semibold text-slate-800 mb-2">{{ $document->title }}</h1>

        {{-- Document number --}}
        <p class="text-sm text-slate-500 mb-6">{{ $document->document_number }}</p>

        {{-- Informational message --}}
        <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 mb-6">
            <p class="text-amber-700 font-medium">Belum ada versi terbit</p>
            <p class="text-amber-600 text-sm mt-1">Dokumen ini belum memiliki revisi yang diterbitkan.</p>
        </div>

        {{-- Back link --}}
        <a href="{{ url('/admin') }}"
            class="inline-flex items-center text-sm text-indigo-600 hover:text-indigo-800 font-medium transition">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18">
                </path>
            </svg>
            Kembali ke Panel Admin
        </a>
    </div>

</body>

</html>
