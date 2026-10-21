<div class="bapr-modal-wrapper">
    <style>
        .bapr-modal-wrapper {
            font-family: inherit;
            color: #1f2937;
        }
        .dark .bapr-modal-wrapper {
            color: #f3f4f6;
        }

        /* Tabs */
        .bapr-tabs-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 12px;
            margin-bottom: 20px;
            gap: 12px;
            flex-wrap: wrap;
        }
        .dark .bapr-tabs-header {
            border-bottom-color: #374151;
        }
        .bapr-tabs-nav {
            display: inline-flex;
            background-color: #f3f4f6;
            border-radius: 8px;
            padding: 4px;
            gap: 4px;
        }
        .dark .bapr-tabs-nav {
            background-color: #1f2937;
        }
        .bapr-tab-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 14px;
            font-size: 13px;
            font-weight: 500;
            border-radius: 6px;
            border: none;
            background: transparent;
            color: #6b7280;
            cursor: pointer;
            transition: all 0.15s ease-in-out;
        }
        .dark .bapr-tab-btn {
            color: #9ca3af;
        }
        .bapr-tab-btn:hover {
            color: #111827;
        }
        .dark .bapr-tab-btn:hover {
            color: #ffffff;
        }
        .bapr-tab-btn.active {
            background-color: #ffffff;
            color: #d97706;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            font-weight: 600;
        }
        .dark .bapr-tab-btn.active {
            background-color: #374151;
            color: #fbbf24;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);
        }
        .bapr-tabs-helper {
            font-size: 12px;
            color: #6b7280;
            margin: 0;
        }
        .dark .bapr-tabs-helper {
            color: #9ca3af;
        }

        /* 2-Column Calendar Layout */
        .bapr-calendar-layout {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 20px;
        }
        @media (max-width: 900px) {
            .bapr-calendar-layout {
                grid-template-columns: 1fr;
            }
        }

        /* Calendar Card */
        .bapr-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        .dark .bapr-card {
            background: #111827;
            border-color: #374151;
            box-shadow: none;
        }

        /* Calendar Header & Controls */
        .bapr-cal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }
        .bapr-cal-title-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .bapr-nav-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            background: #ffffff;
            color: #374151;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .dark .bapr-nav-btn {
            background: #1f2937;
            border-color: #374151;
            color: #e5e7eb;
        }
        .bapr-nav-btn:hover {
            background: #f3f4f6;
            color: #111827;
        }
        .dark .bapr-nav-btn:hover {
            background: #374151;
            color: #ffffff;
        }
        .bapr-month-title {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: #111827;
            min-width: 140px;
            text-align: center;
        }
        .dark .bapr-month-title {
            color: #f9fafb;
        }
        .bapr-today-btn {
            font-size: 12px;
            font-weight: 600;
            color: #d97706;
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
            transition: background-color 0.15s ease;
        }
        .dark .bapr-today-btn {
            color: #fbbf24;
        }
        .bapr-today-btn:hover {
            text-decoration: underline;
            background: rgba(217, 119, 6, 0.08);
        }

        /* Days of Week Row */
        .bapr-days-header-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 4px;
            text-align: center;
            margin-bottom: 8px;
        }
        .bapr-day-name {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #9ca3af;
            padding: 4px 0;
        }
        .dark .bapr-day-name {
            color: #6b7280;
        }

        /* Month Days Grid */
        .bapr-cal-weeks-grid {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .bapr-cal-row {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 4px;
        }
        .bapr-day-cell {
            position: relative;
            height: 44px;
            border-radius: 8px;
            border: 1px solid transparent;
            background: transparent;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s ease-in-out;
            padding: 2px;
        }
        .bapr-day-cell:hover:not(:disabled) {
            background: #f3f4f6;
        }
        .dark .bapr-day-cell:hover:not(:disabled) {
            background: #1f2937;
        }
        .bapr-day-cell:disabled {
            opacity: 0.25;
            cursor: default;
        }
        .bapr-day-cell.selected {
            border-color: #d97706 !important;
            background: rgba(217, 119, 6, 0.08) !important;
            box-shadow: 0 0 0 1px #d97706;
        }
        .dark .bapr-day-cell.selected {
            border-color: #fbbf24 !important;
            background: rgba(251, 191, 36, 0.12) !important;
            box-shadow: 0 0 0 1px #fbbf24;
        }
        .bapr-day-cell.today:not(.selected) {
            border-color: #cbd5e1;
            font-weight: 600;
        }
        .dark .bapr-day-cell.today:not(.selected) {
            border-color: #475569;
        }
        .bapr-day-cell.has-bapr {
            background: rgba(16, 185, 129, 0.05);
            border-color: rgba(16, 185, 129, 0.25);
        }
        .dark .bapr-day-cell.has-bapr {
            background: rgba(16, 185, 129, 0.08);
            border-color: rgba(16, 185, 129, 0.3);
        }

        .bapr-day-num {
            font-size: 13px;
            font-weight: 500;
            color: #374151;
            line-height: 1;
        }
        .dark .bapr-day-num {
            color: #e5e7eb;
        }
        .bapr-day-cell.has-bapr .bapr-day-num {
            font-weight: 700;
            color: #047857;
        }
        .dark .bapr-day-cell.has-bapr .bapr-day-num {
            color: #34d399;
        }

        /* Indicator Dot & Count */
        .bapr-indicator-wrap {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            margin-top: 3px;
            height: 8px;
        }
        .bapr-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background-color: #10b981;
            box-shadow: 0 0 4px rgba(16, 185, 129, 0.6);
            display: inline-block;
        }
        .bapr-count-badge {
            font-size: 9px;
            font-weight: 700;
            color: #047857;
            line-height: 1;
        }
        .dark .bapr-count-badge {
            color: #34d399;
        }
        .bapr-dot-placeholder {
            width: 6px;
            height: 6px;
            display: inline-block;
        }

        /* Calendar Legend */
        .bapr-cal-legend {
            margin-top: 14px;
            padding-top: 12px;
            border-top: 1px solid #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 11px;
            color: #6b7280;
        }
        .dark .bapr-cal-legend {
            border-top-color: #1f2937;
            color: #9ca3af;
        }
        .bapr-legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .bapr-legend-box {
            width: 10px;
            height: 10px;
            border-radius: 3px;
            border: 1px solid #d97706;
            background: rgba(217, 119, 6, 0.15);
            display: inline-block;
        }

        /* Results Panel (Right Column) */
        .bapr-results-panel {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 380px;
        }
        .dark .bapr-results-panel {
            background: #111827;
            border-color: #374151;
        }
        .bapr-results-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .dark .bapr-results-header {
            border-bottom-color: #374151;
        }
        .bapr-results-title {
            font-size: 13px;
            font-weight: 700;
            color: #111827;
            margin: 0;
        }
        .dark .bapr-results-title {
            color: #f9fafb;
        }
        .bapr-results-sub {
            font-size: 12px;
            color: #6b7280;
            margin: 2px 0 0 0;
        }
        .dark .bapr-results-sub {
            color: #9ca3af;
        }
        .bapr-badge-count {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            background: #d1fae5;
            color: #065f46;
        }
        .dark .bapr-badge-count {
            background: #064e3b;
            color: #6ee7b7;
        }

        /* Group Card */
        .bapr-group-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-height: 290px;
            overflow-y: auto;
            padding-right: 4px;
        }
        .bapr-group-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 12px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
            transition: border-color 0.15s ease;
        }
        .dark .bapr-group-card {
            background: #1f2937;
            border-color: #374151;
        }
        .bapr-group-card:hover {
            border-color: #d97706;
        }
        .bapr-card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 8px;
        }
        .bapr-num-code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 12px;
            font-weight: 700;
            color: #111827;
        }
        .dark .bapr-num-code {
            color: #f9fafb;
        }
        .bapr-status-pill {
            display: inline-flex;
            align-items: center;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
        }
        .bapr-status-pill.final {
            background: #dbeafe;
            color: #1e40af;
        }
        .dark .bapr-status-pill.final {
            background: #1e3a8a;
            color: #93c5fd;
        }
        .bapr-status-pill.open {
            background: #f3f4f6;
            color: #4b5563;
        }
        .dark .bapr-status-pill.open {
            background: #374151;
            color: #d1d5db;
        }
        .bapr-meta-row {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 11px;
            color: #6b7280;
            margin-bottom: 10px;
        }
        .dark .bapr-meta-row {
            color: #9ca3af;
        }
        .bapr-meta-item {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        /* Action Buttons */
        .bapr-btn-download {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 6px;
            background: #059669;
            color: #ffffff;
            font-size: 12px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            text-decoration: none;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            transition: all 0.15s ease;
        }
        .bapr-btn-download:hover {
            background: #047857;
            color: #ffffff;
        }
        .bapr-btn-download:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .bapr-clear-btn {
            background: none;
            border: none;
            font-size: 11px;
            color: #6b7280;
            cursor: pointer;
            padding: 4px 6px;
            border-radius: 4px;
        }
        .bapr-clear-btn:hover {
            color: #111827;
            text-decoration: underline;
        }
        .dark .bapr-clear-btn {
            color: #9ca3af;
        }
        .dark .bapr-clear-btn:hover {
            color: #ffffff;
        }

        /* Empty State */
        .bapr-empty-box {
            text-align: center;
            padding: 36px 16px;
            color: #6b7280;
        }
        .dark .bapr-empty-box {
            color: #9ca3af;
        }
        .bapr-empty-icon {
            width: 36px;
            height: 36px;
            margin: 0 auto 10px auto;
            color: #9ca3af;
            display: block;
        }
        .bapr-empty-title {
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin: 0 0 4px 0;
        }
        .dark .bapr-empty-title {
            color: #d1d5db;
        }
        .bapr-empty-desc {
            font-size: 11px;
            color: #9ca3af;
            margin: 0;
        }

        /* Search Tab */
        .bapr-search-wrapper {
            position: relative;
            margin-bottom: 14px;
        }
        .bapr-search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            color: #9ca3af;
            pointer-events: none;
        }
        .bapr-search-input {
            width: 100%;
            padding: 8px 12px 8px 36px;
            font-size: 13px;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            background: #ffffff;
            color: #111827;
            outline: none;
            box-sizing: border-box;
            transition: border-color 0.15s ease;
        }
        .dark .bapr-search-input {
            background: #1f2937;
            border-color: #374151;
            color: #f9fafb;
        }
        .bapr-search-input:focus {
            border-color: #d97706;
            box-shadow: 0 0 0 1px #d97706;
        }

        /* Table */
        .bapr-table-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
        }
        .dark .bapr-table-card {
            background: #111827;
            border-color: #374151;
        }
        .bapr-table-responsive {
            width: 100%;
            overflow-x: auto;
        }
        .bapr-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            text-align: left;
        }
        .bapr-table th {
            background: #f9fafb;
            color: #4b5563;
            font-weight: 600;
            padding: 10px 14px;
            border-bottom: 1px solid #e5e7eb;
            white-space: nowrap;
        }
        .dark .bapr-table th {
            background: #1f2937;
            color: #9ca3af;
            border-bottom-color: #374151;
        }
        .bapr-table td {
            padding: 10px 14px;
            border-bottom: 1px solid #f3f4f6;
            color: #1f2937;
        }
        .dark .bapr-table td {
            border-bottom-color: #1f2937;
            color: #e5e7eb;
        }
        .bapr-table tr:hover td {
            background: #fafafa;
        }
        .dark .bapr-table tr:hover td {
            background: #182234;
        }
        .bapr-table-pagination {
            padding: 12px 14px;
            border-top: 1px solid #e5e7eb;
        }
        .dark .bapr-table-pagination {
            border-top-color: #374151;
        }

        /* General Icons */
        .bapr-icon-sm {
            width: 14px;
            height: 14px;
            display: inline-block;
            vertical-align: middle;
            flex-shrink: 0;
        }
        .bapr-icon-md {
            width: 18px;
            height: 18px;
            display: inline-block;
            vertical-align: middle;
            flex-shrink: 0;
        }
    </style>

    {{-- Segmented Tab Navigation --}}
    <div class="bapr-tabs-header">
        <div class="bapr-tabs-nav">
            <button
                type="button"
                wire:click="$set('activeTab', 'calendar')"
                class="bapr-tab-btn {{ $activeTab === 'calendar' ? 'active' : '' }}"
            >
                <svg class="bapr-icon-sm" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                Kalender Ketersediaan
            </button>
            <button
                type="button"
                wire:click="$set('activeTab', 'list')"
                class="bapr-tab-btn {{ $activeTab === 'list' ? 'active' : '' }}"
            >
                <svg class="bapr-icon-sm" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                </svg>
                Cari &amp; Daftar BAPR
            </button>
        </div>

        <p class="bapr-tabs-helper">
            Pilih tanggal bertanda hijau atau cari berdasarkan nomor BAPR/nama rekaman.
        </p>
    </div>

    {{-- TAB 1: CALENDAR VIEW --}}
    @if ($activeTab === 'calendar')
        <div class="bapr-calendar-layout">
            {{-- Kalender Card (Left Column) --}}
            <div class="bapr-card">
                {{-- Month & Year Header Controls --}}
                <div class="bapr-cal-header">
                    <div class="bapr-cal-title-group">
                        <button
                            type="button"
                            wire:click="previousMonth"
                            aria-label="Bulan sebelumnya"
                            class="bapr-nav-btn"
                        >
                            <svg class="bapr-icon-md" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>
                        <h3 class="bapr-month-title">
                            {{ $monthTitle }}
                        </h3>
                        <button
                            type="button"
                            wire:click="nextMonth"
                            aria-label="Bulan berikutnya"
                            class="bapr-nav-btn"
                        >
                            <svg class="bapr-icon-md" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>

                    <button
                        type="button"
                        wire:click="goToCurrentMonth"
                        class="bapr-today-btn"
                    >
                        Bulan Ini
                    </button>
                </div>

                {{-- Days of Week Header --}}
                <div class="bapr-days-header-grid">
                    <div class="bapr-day-name">Sen</div>
                    <div class="bapr-day-name">Sel</div>
                    <div class="bapr-day-name">Rab</div>
                    <div class="bapr-day-name">Kam</div>
                    <div class="bapr-day-name">Jum</div>
                    <div class="bapr-day-name">Sab</div>
                    <div class="bapr-day-name">Min</div>
                </div>

                {{-- Calendar Weeks Grid --}}
                <div class="bapr-cal-weeks-grid">
                    @foreach ($weeks as $week)
                        <div class="bapr-cal-row">
                            @foreach ($week as $day)
                                @php
                                    $isCurrent = $day['isCurrentMonth'];
                                    $hasBapr = $day['hasBapr'];
                                    $isSelected = $day['isSelected'];
                                    $isToday = $day['isToday'];
                                @endphp

                                <button
                                    type="button"
                                    wire:click="selectDate('{{ $day['date'] }}')"
                                    @if (! $isCurrent) disabled @endif
                                    aria-label="{{ $day['date'] }}{{ $hasBapr ? ' (Tersedia BAPR)' : '' }}"
                                    class="bapr-day-cell {{ $isSelected ? 'selected' : '' }} {{ $isToday && ! $isSelected ? 'today' : '' }} {{ $hasBapr ? 'has-bapr' : '' }}"
                                >
                                    <span class="bapr-day-num">
                                        {{ $day['day'] }}
                                    </span>

                                    {{-- Availability Indicator Dot --}}
                                    @if ($hasBapr)
                                        <div class="bapr-indicator-wrap">
                                            <span
                                                class="bapr-dot"
                                                title="{{ $day['baprCount'] }} BAPR tersedia"
                                            ></span>
                                            @if ($day['baprCount'] > 1)
                                                <span class="bapr-count-badge">
                                                    {{ $day['baprCount'] }}
                                                </span>
                                            @endif
                                        </div>
                                    @else
                                        <div class="bapr-indicator-wrap">
                                            <span class="bapr-dot-placeholder"></span>
                                        </div>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    @endforeach
                </div>

                {{-- Legend --}}
                <div class="bapr-cal-legend">
                    <div class="bapr-legend-item">
                        <span class="bapr-dot"></span>
                        <span>Ada BAPR Tersedia</span>
                    </div>
                    <div class="bapr-legend-item">
                        <span class="bapr-legend-box"></span>
                        <span>Tanggal Terpilih</span>
                    </div>
                </div>
            </div>

            {{-- Selected Date Results Panel (Right Column) --}}
            <div class="bapr-results-panel">
                <div>
                    <div class="bapr-results-header">
                        <div>
                            <h4 class="bapr-results-title">
                                BAPR Tanggal:
                            </h4>
                            <p class="bapr-results-sub">
                                @if ($selectedDate)
                                    {{ \Carbon\Carbon::parse($selectedDate)->translatedFormat('d F Y') }}
                                @else
                                    Pilih tanggal pada kalender
                                @endif
                            </p>
                        </div>

                        @if ($selectedDate)
                            <span class="bapr-badge-count">
                                {{ $selectedDateGroups->count() }} BAPR
                            </span>
                        @endif
                    </div>

                    @if (! $selectedDate)
                        <div class="bapr-empty-box">
                            <svg class="bapr-empty-icon" width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <p class="bapr-empty-title">Pilih Tanggal</p>
                            <p class="bapr-empty-desc">Klik salah satu tanggal pada kalender untuk melihat dan mengunduh BAPR.</p>
                        </div>
                    @elseif ($selectedDateGroups->isEmpty())
                        <div class="bapr-empty-box">
                            <svg class="bapr-empty-icon" width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <p class="bapr-empty-title">Tidak ada BAPR</p>
                            <p class="bapr-empty-desc">Tidak ada BAPR pada tanggal ini. Pilih tanggal bertanda hijau.</p>
                        </div>
                    @else
                        <div class="bapr-group-list">
                            @foreach ($selectedDateGroups as $group)
                                @php
                                    $recCount = $group->isFinalized() && ! empty($group->snapshot_data)
                                        ? count($group->snapshot_data)
                                        : $group->records_count;
                                @endphp

                                <div class="bapr-group-card">
                                    <div class="bapr-card-top">
                                        <div class="bapr-num-code">
                                            {{ $group->bapr_number }}
                                        </div>

                                        <span class="bapr-status-pill {{ $group->isFinalized() ? 'final' : 'open' }}">
                                            {{ $group->isFinalized() ? 'Final' : 'Terbuka' }}
                                        </span>
                                    </div>

                                    <div class="bapr-meta-row">
                                        <span class="bapr-meta-item">
                                            <svg class="bapr-icon-sm" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                            </svg>
                                            <strong>{{ $recCount }}</strong> Rekaman
                                        </span>
                                        <span>•</span>
                                        <span>{{ $group->destruction_date->format('d/m/Y') }}</span>
                                    </div>

                                    <div style="text-align: right; border-top: 1px solid #f3f4f6; padding-top: 8px;">
                                        <button
                                            type="button"
                                            wire:click="downloadGroup({{ $group->id }})"
                                            wire:loading.attr="disabled"
                                            class="bapr-btn-download"
                                        >
                                            <svg class="bapr-icon-sm" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                            </svg>
                                            <span wire:loading.remove wire:target="downloadGroup({{ $group->id }})">Unduh Excel</span>
                                            <span wire:loading wire:target="downloadGroup({{ $group->id }})">Mengunduh...</span>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                @if ($selectedDate)
                    <div style="border-top: 1px solid #e5e7eb; padding-top: 10px; margin-top: 12px; text-align: right;">
                        <button
                            type="button"
                            wire:click="clearSelectedDate"
                            class="bapr-clear-btn"
                        >
                            Bersihkan Pilihan
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- TAB 2: SEARCHABLE LIST VIEW --}}
    @if ($activeTab === 'list')
        <div>
            {{-- Search Bar --}}
            <div class="bapr-search-wrapper">
                <svg class="bapr-search-icon" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Cari berdasarkan nomor BAPR, tanggal (YYYY-MM-DD), nama rekaman, atau nomor formulir..."
                    class="bapr-search-input"
                />
            </div>

            {{-- Results Table --}}
            <div class="bapr-table-card">
                @if ($searchResults && $searchResults->isNotEmpty())
                    <div class="bapr-table-responsive">
                        <table class="bapr-table">
                            <thead>
                                <tr>
                                    <th>Tanggal Pemusnahan</th>
                                    <th>Nomor BAPR</th>
                                    <th>Cara Pemusnahan</th>
                                    <th>Jumlah Rekaman</th>
                                    <th>Status</th>
                                    <th style="text-align: right;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($searchResults as $group)
                                    @php
                                        $recCount = $group->isFinalized() && ! empty($group->snapshot_data)
                                            ? count($group->snapshot_data)
                                            : $group->records_count;
                                    @endphp

                                    <tr>
                                        <td style="font-weight: 600;">
                                            {{ $group->destruction_date->format('d/m/Y') }}
                                        </td>
                                        <td style="font-family: monospace; font-weight: 700;">
                                            {{ $group->bapr_number }}
                                        </td>
                                        <td style="color: #6b7280;">
                                            {{ $group->method }}
                                        </td>
                                        <td>
                                            <strong>{{ $recCount }}</strong> Rekaman
                                        </td>
                                        <td>
                                            <span class="bapr-status-pill {{ $group->isFinalized() ? 'final' : 'open' }}">
                                                {{ $group->isFinalized() ? 'Final' : 'Terbuka' }}
                                            </span>
                                        </td>
                                        <td style="text-align: right;">
                                            <button
                                                type="button"
                                                wire:click="downloadGroup({{ $group->id }})"
                                                wire:loading.attr="disabled"
                                                class="bapr-btn-download"
                                            >
                                                <svg class="bapr-icon-sm" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                </svg>
                                                <span wire:loading.remove wire:target="downloadGroup({{ $group->id }})">Unduh Excel</span>
                                                <span wire:loading wire:target="downloadGroup({{ $group->id }})">Mengunduh...</span>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Pagination Controls --}}
                    <div class="bapr-table-pagination">
                        {{ $searchResults->links() }}
                    </div>
                @else
                    <div class="bapr-empty-box">
                        <svg class="bapr-empty-icon" width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <p class="bapr-empty-title">Tidak ditemukan BAPR yang cocok</p>
                        <p class="bapr-empty-desc">Coba gunakan kata kunci pencarian yang lain.</p>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
