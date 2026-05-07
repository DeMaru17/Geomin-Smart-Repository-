<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mode Aman - {{ $revision->document->title ?? 'Dokumen' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        /* Canvas watermark: fixed di atas seluruh viewport, tidak bisa diklik */
        #watermark-overlay {
            position: fixed;
            inset: 0;
            z-index: 9999;
            pointer-events: none;
            user-select: none;
        }
    </style>
</head>
<body class="bg-[#1e293b] text-white h-screen flex flex-col overflow-hidden" oncontextmenu="return false;">

    {{-- Navbar --}}
    <div class="bg-[#0f172a] border-b border-slate-700 flex items-center justify-between px-6 py-4 shadow-md z-10">
        <div class="flex items-center space-x-4">
            <a href="javascript:history.back()" class="text-slate-400 hover:text-white transition flex items-center">
                <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Kembali
            </a>
            <div class="h-6 w-px bg-slate-600 mx-2"></div>
            <h1 class="font-semibold text-lg flex items-center">
                <span class="bg-indigo-600 text-xs px-2 py-1 rounded mr-3">{{ $revision->document->document_number ?? 'No. Dokumen' }}</span>
                {{ $revision->document->title ?? 'Judul Dokumen' }}
                <span class="text-slate-400 font-normal ml-2 text-sm">Rev. {{ $revision->revision_number }}</span>
            </h1>
        </div>
        <div>
            <span class="border border-emerald-500 text-emerald-400 px-3 py-1 rounded-full text-xs font-bold flex items-center">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.956 11.956 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                Mode Aman Aktif
            </span>
        </div>
    </div>

    {{-- Banner peringatan --}}
    <div class="bg-amber-900/40 border-b border-amber-700 text-amber-500 text-sm px-6 py-2.5 flex items-center justify-center shadow-inner z-10">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
        Dokumen ini dilindungi. Dilarang mengunduh, menyalin, atau mereproduksi konten tanpa izin. Akses tercatat atas nama <span class="font-bold ml-1 text-amber-400">{{ auth()->user()->name }}</span>
    </div>

    {{-- Area PDF viewer --}}
    <div
        x-data="{
            loading: true,
            init() {
                const script = document.createElement('script');
                script.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js';
                script.onload = () => this.renderPDF();
                document.head.appendChild(script);

                this.drawWatermark();
            },
            drawWatermark() {
                const canvas = document.getElementById('watermark-overlay');
                const ctx    = canvas.getContext('2d');

                const render = () => {
                    canvas.width  = window.innerWidth;
                    canvas.height = window.innerHeight;

                    // Tile offscreen: lebar & tinggi sesuai spacing antar teks
                    const tileW = 550;
                    const tileH = 280;
                    const angle = -30 * Math.PI / 180;

                    const tile = document.createElement('canvas');
                    tile.width  = tileW;
                    tile.height = tileH;
                    const tc = tile.getContext('2d');

                    tc.save();
                    tc.translate(tileW / 2, tileH / 2);
                    tc.rotate(angle);

                    // Nama user — lebih tebal & lebih visible
                    tc.font = 'bold 19px Arial';
                    tc.fillStyle = 'rgba(0, 0, 0, 0.15)';
                    tc.textAlign = 'center';
                    tc.fillText('{{ auth()->user()->name }}', 0, -8);

                    // Timestamp akses
                    const now = new Date();
                    const ts  = now.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
                              + '  ' + now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
                    tc.font = '13px Arial';
                    tc.fillStyle = 'rgba(0, 0, 0, 0.11)';
                    tc.fillText(ts, 0, 14);

                    tc.restore();

                    const pattern  = ctx.createPattern(tile, 'repeat');
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    ctx.fillStyle  = pattern;
                    ctx.fillRect(0, 0, canvas.width, canvas.height);
                };

                render();
                window.addEventListener('resize', render);
            },
            renderPDF() {
                pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';

                pdfjsLib.getDocument('{{ route('pdf.stream', $revision->id) }}').promise.then(pdf => {
                    this.loading = false;
                    for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
                        pdf.getPage(pageNum).then(page => {
                            const viewport = page.getViewport({ scale: 1.5 });
                            const canvas   = document.createElement('canvas');
                            const context  = canvas.getContext('2d');
                            canvas.height  = viewport.height;
                            canvas.width   = viewport.width;
                            canvas.className = 'shadow-2xl rounded-sm bg-white mb-8 max-w-full h-auto';

                            this.$refs.container.appendChild(canvas);
                            page.render({ canvasContext: context, viewport });
                        });
                    }
                }).catch(() => {
                    this.loading = false;
                    alert('Gagal memuat dokumen.');
                });
            }
        }"
        @keydown.window="(e) => {
            if ((e.ctrlKey || e.metaKey) && ['s','p','c'].includes(e.key)) {
                e.preventDefault();
                alert('Sistem Keamanan: Tindakan dilarang.');
            }
        }"
        class="flex-1 overflow-y-auto p-4 md:p-8 flex flex-col items-center relative"
    >
        <div x-show="loading" class="text-white text-lg animate-pulse font-semibold mt-20">Memuat dokumen aman...</div>
        <div x-ref="container" class="w-full flex flex-col items-center z-10"></div>
    </div>

    {{-- Canvas watermark: fixed di luar scroll container, selalu di atas seluruh viewport --}}
    <canvas id="watermark-overlay"></canvas>

</body>
</html>