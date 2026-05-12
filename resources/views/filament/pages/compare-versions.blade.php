<x-filament-panels::page>
    {{-- Menghilangkan space-y bawaan agar jarak bisa kita atur secara presisi --}}
    <div>
        {{-- Memberikan margin bawah (mb-6 / 24px) antara teks dan form filter --}}
        <header style="margin-bottom: 24px;">
            <p class="text-sm text-gray-500">Tampilan side-by-side untuk membandingkan draf revisi dengan versi sebelumnya</p>
        </header>

        {{-- Form Selection dengan margin bawah (mb-8 / 32px) agar menjauh dari tabel/placeholder --}}
        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 p-4" style="margin-bottom: 32px;">
            {{ $this->form }}
        </div>

        @if($diffHtml)
            {{-- Container Utama Diff --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 overflow-hidden shadow-sm">
                
                <style>
                    {!! \Jfcherng\Diff\DiffHelper::getStyleSheet() !!}

                    .diff-wrapper {
                        font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
                        font-size: 13px;
                        width: 100%;
                        overflow: hidden !important;
                        max-width: 100%;
                    }

                    /* TABLE */
                    .diff-wrapper table.diff {
                        width: 100%;
                        max-width: 100%;
                        table-layout: fixed !important;
                        border-collapse: collapse;
                        border-spacing: 0;
                        overflow: hidden;
                    }

                    .diff.diff-html.side-by-side thead {
                        display: none;
                    }

                    /* CELL */
                    .diff.diff-html.side-by-side td {
                        overflow: hidden !important;
                        position: relative;
                        vertical-align: top;
                        box-sizing: border-box;
                        max-width: 0;
                    }

                    /* KOLOM */
                    .diff.diff-html.side-by-side td.diff-old,
                    .diff.diff-html.side-by-side td.diff-new {
                        width: 48%;
                        max-width: 48%;
                        overflow: hidden !important;
                    }

                    .diff.diff-html.side-by-side td.diff-symbol {
                        width: 4%;
                        background: #f9fafb;
                        border-left: 1px solid #e5e7eb;
                        border-right: 1px solid #e5e7eb;
                        text-align: center;
                        color: #9ca3af;
                        padding: 0.75rem 0.25rem;
                        overflow: hidden;
                    }

                    /* SEMUA KONTEN INTERNAL */
                    .diff.diff-html.side-by-side td * {
                        max-width: 100% !important;
                        box-sizing: border-box !important;
                        white-space: pre-wrap !important;
                        word-break: break-all !important;
                        overflow-wrap: anywhere !important;
                        overflow: hidden !important;
                    }

                    /* KHUSUS LINE CONTENT */
                    .diff-line {
                        max-width: 100% !important;
                        overflow: hidden !important;
                        display: block !important;
                    }

                    /* PRE */
                    .diff.diff-html.side-by-side pre {
                        margin: 0 !important;
                        max-width: 100% !important;
                        overflow: hidden !important;
                        white-space: pre-wrap !important;
                        word-break: break-all !important;
                        overflow-wrap: anywhere !important;
                    }

                    /* HILANGKAN BG DEFAULT */
                    .diff.diff-html.side-by-side .change-added,
                    .diff.diff-html.side-by-side .change-deleted,
                    .diff.diff-html.side-by-side .change-repmod {
                        background-color: transparent !important;
                    }

                    /* INSERT */
                    ins {
                        display: inline;
                        background: transparent !important;
                        color: #16a34a !important;
                        text-decoration: none;
                        font-weight: 700;
                        word-break: break-all !important;
                        overflow-wrap: anywhere !important;
                        white-space: pre-wrap !important;
                        max-width: 100% !important;
                        overflow: hidden !important;
                    }

                    /* DELETE */
                    del {
                        display: inline;
                        background: transparent !important;
                        color: #dc2626 !important;
                        text-decoration: line-through;
                        font-weight: 700;
                        word-break: break-all !important;
                        overflow-wrap: anywhere !important;
                        white-space: pre-wrap !important;
                        max-width: 100% !important;
                        overflow: hidden !important;
                    }
                </style>

                <div class="diff-wrapper">
                    {!! $diffHtml !!}
                </div>
            </div>
        @else
            {{-- Card Placeholder --}}
            <div style="background-color: #ffffff; border-radius: 12px; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); border: 1px solid #e5e7eb; padding: 64px 20px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;">
                <div style="width: 80px; height: 80px; background-color: #f9fafb; border: 1px solid #f3f4f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 20px;">
                    <x-heroicon-o-document-magnifying-glass style="width: 40px; height: 40px; color: #9ca3af;" />
                </div>
                <h3 style="font-size: 1.25rem; font-weight: 600; color: #111827; margin: 0 0 8px 0;">
                    Pilih dokumen dan revisi untuk dibandingkan
                </h3>
                <p style="font-size: 0.875rem; color: #6b7280; max-width: 420px; margin: 0; line-height: 1.5;">
                    Silakan pilih dokumen, lalu tentukan versi lama dan versi baru pada form di atas untuk melihat rincian perubahan secara berdampingan.
                </p>
            </div>
        @endif
    </div>
</x-filament-panels::page>