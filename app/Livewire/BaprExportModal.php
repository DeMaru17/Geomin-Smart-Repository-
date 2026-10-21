<?php

namespace App\Livewire;

use App\Models\QualityRecordDestructionGroup;
use App\Services\QualityRecordDestructionExportService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class BaprExportModal extends Component
{
    use WithPagination;

    public string $activeTab = 'calendar'; // 'calendar' | 'list'

    public int $currentYear;

    public int $currentMonth;

    public ?string $selectedDate = null;

    public string $search = '';

    protected array $monthNames = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    public function mount(): void
    {
        $timezone = config('app.timezone', 'Asia/Jakarta');
        $now = now()->setTimezone($timezone);

        $this->currentYear = $now->year;
        $this->currentMonth = $now->month;

        $availableDates = $this->getAvailableDates();
        $todayStr = $now->toDateString();

        if (isset($availableDates[$todayStr])) {
            $this->selectedDate = $todayStr;
        } elseif (! empty($availableDates)) {
            $dates = array_keys($availableDates);
            $this->selectedDate = end($dates);
        }
    }

    public function previousMonth(): void
    {
        if ($this->currentMonth === 1) {
            $this->currentMonth = 12;
            $this->currentYear--;
        } else {
            $this->currentMonth--;
        }

        $this->selectedDate = null;
    }

    public function nextMonth(): void
    {
        if ($this->currentMonth === 12) {
            $this->currentMonth = 1;
            $this->currentYear++;
        } else {
            $this->currentMonth++;
        }

        $this->selectedDate = null;
    }

    public function goToCurrentMonth(): void
    {
        $timezone = config('app.timezone', 'Asia/Jakarta');
        $now = now()->setTimezone($timezone);

        $this->currentYear = $now->year;
        $this->currentMonth = $now->month;
        $this->selectedDate = $now->toDateString();
    }

    public function selectDate(string $date): void
    {
        $this->selectedDate = $date;
    }

    public function clearSelectedDate(): void
    {
        $this->selectedDate = null;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Download BAPR Excel file for the given group ID.
     */
    public function downloadGroup(int $groupId)
    {
        if (! auth()->check() || ! in_array(auth()->user()->role, ['admin', 'manajemen'])) {
            Notification::make()
                ->title('Akses Ditolak')
                ->body('Anda tidak memiliki wewenang untuk mengunduh BAPR.')
                ->danger()
                ->send();

            return;
        }

        $group = QualityRecordDestructionGroup::with('records')->find($groupId);

        if (! $group) {
            Notification::make()
                ->title('Grup BAPR tidak ditemukan')
                ->danger()
                ->send();

            return;
        }

        if ($group->records->isEmpty() && empty($group->snapshot_data)) {
            Notification::make()
                ->title('Tidak Ada Rekaman')
                ->body('Grup BAPR ini tidak memiliki rekaman yang terkait.')
                ->warning()
                ->send();

            return;
        }

        $service = new QualityRecordDestructionExportService();
        $result = $service->export($group);

        if ($result === false) {
            Notification::make()
                ->title('File template tidak tersedia')
                ->body('File template 273.106.208 Berita Acara Pemusnahan Rekaman Rev00.xlsx tidak ditemukan di storage.')
                ->danger()
                ->send();

            return;
        }

        $filename = $service->getFilename($group);

        return response()->download($result, $filename)->deleteFileAfterSend();
    }

    /**
     * Ambil pemetaan tanggal yang memiliki grup BAPR pada bulan yang sedang ditampilkan.
     * Menggunakan query agregat tunggal tanpa N+1.
     *
     * @return array<string, int> ['YYYY-MM-DD' => count]
     */
    public function getAvailableDates(): array
    {
        $timezone = config('app.timezone', 'Asia/Jakarta');
        $startOfMonth = Carbon::create($this->currentYear, $this->currentMonth, 1, 0, 0, 0, $timezone)->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        return QualityRecordDestructionGroup::query()
            ->where(function ($q) {
                $q->has('records')
                    ->orWhereNotNull('snapshot_data');
            })
            ->whereBetween('destruction_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->select('destruction_date', DB::raw('count(*) as count'))
            ->groupBy('destruction_date')
            ->get()
            ->mapWithKeys(function ($row) {
                $dateKey = $row->destruction_date instanceof Carbon
                    ? $row->destruction_date->toDateString()
                    : Carbon::parse($row->destruction_date)->toDateString();

                return [$dateKey => (int) $row->count];
            })
            ->toArray();
    }

    /**
     * Hitung matriks kalender (minggu dan hari) untuk bulan yang dipilih.
     */
    public function getCalendarWeeks(): array
    {
        $timezone = config('app.timezone', 'Asia/Jakarta');
        $firstDayOfMonth = Carbon::create($this->currentYear, $this->currentMonth, 1, 0, 0, 0, $timezone);
        $daysInMonth = $firstDayOfMonth->daysInMonth;
        $availableDates = $this->getAvailableDates();
        $today = now()->setTimezone($timezone)->toDateString();

        // 1 (Senin) sampai 7 (Minggu)
        $startDayOfWeek = $firstDayOfMonth->dayOfWeekIso;

        $weeks = [];
        $currentWeek = [];

        // Padding hari sebelum awal bulan dari bulan sebelumnya
        $prevMonthLastDay = $firstDayOfMonth->copy()->subDay();
        $prevMonthDaysToShow = $startDayOfWeek - 1;

        for ($i = $prevMonthDaysToShow - 1; $i >= 0; $i--) {
            $prevDate = $prevMonthLastDay->copy()->subDays($i);
            $currentWeek[] = [
                'date' => $prevDate->toDateString(),
                'day' => $prevDate->day,
                'isCurrentMonth' => false,
                'isToday' => $prevDate->toDateString() === $today,
                'hasBapr' => false,
                'baprCount' => 0,
                'isSelected' => false,
            ];
        }

        // Hari-hari di bulan berjalan
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateCarbon = Carbon::create($this->currentYear, $this->currentMonth, $day, 0, 0, 0, $timezone);
            $dateStr = $dateCarbon->toDateString();
            $hasBapr = isset($availableDates[$dateStr]);
            $baprCount = $availableDates[$dateStr] ?? 0;

            $currentWeek[] = [
                'date' => $dateStr,
                'day' => $day,
                'isCurrentMonth' => true,
                'isToday' => $dateStr === $today,
                'hasBapr' => $hasBapr,
                'baprCount' => $baprCount,
                'isSelected' => $this->selectedDate === $dateStr,
            ];

            if (count($currentWeek) === 7) {
                $weeks[] = $currentWeek;
                $currentWeek = [];
            }
        }

        // Padding sisa hari untuk menutup minggu terakhir
        if (! empty($currentWeek)) {
            $nextMonthDate = $firstDayOfMonth->copy()->endOfMonth()->addDay();
            $remaining = 7 - count($currentWeek);
            for ($i = 0; $i < $remaining; $i++) {
                $dateObj = $nextMonthDate->copy()->addDays($i);
                $currentWeek[] = [
                    'date' => $dateObj->toDateString(),
                    'day' => $dateObj->day,
                    'isCurrentMonth' => false,
                    'isToday' => $dateObj->toDateString() === $today,
                    'hasBapr' => false,
                    'baprCount' => 0,
                    'isSelected' => false,
                ];
            }
            $weeks[] = $currentWeek;
        }

        return $weeks;
    }

    /**
     * Ambil data grup BAPR pada tanggal yang sedang dipilih.
     */
    public function getSelectedDateGroups()
    {
        if (! $this->selectedDate) {
            return collect();
        }

        return QualityRecordDestructionGroup::query()
            ->where(function ($q) {
                $q->has('records')
                    ->orWhereNotNull('snapshot_data');
            })
            ->withCount('records')
            ->whereDate('destruction_date', $this->selectedDate)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Ambil data hasil pencarian BAPR dengan paginasi.
     */
    public function getSearchResults()
    {
        $query = QualityRecordDestructionGroup::query()
            ->where(function ($q) {
                $q->has('records')
                    ->orWhereNotNull('snapshot_data');
            })
            ->withCount('records');

        if (! empty(trim($this->search))) {
            $term = trim($this->search);
            $query->where(function ($q) use ($term) {
                $q->where('bapr_number', 'like', "%{$term}%")
                    ->orWhere('destruction_date', 'like', "%{$term}%")
                    ->orWhere('method', 'like', "%{$term}%")
                    ->orWhere('snapshot_data', 'like', "%{$term}%")
                    ->orWhereHas('records', function ($rq) use ($term) {
                        $rq->where('record_name', 'like', "%{$term}%")
                            ->orWhere('destruction_method', 'like', "%{$term}%")
                            ->orWhereHas('document', function ($docQ) use ($term) {
                                $docQ->where('document_number', 'like', "%{$term}%")
                                    ->orWhere('title', 'like', "%{$term}%");
                            });
                    });
            });
        }

        $query->orderByDesc('destruction_date')->orderByDesc('id');

        return $query->paginate(6);
    }

    public function render()
    {
        $monthTitle = ($this->monthNames[$this->currentMonth] ?? '') . ' ' . $this->currentYear;

        return view('livewire.bapr-export-modal', [
            'monthTitle' => $monthTitle,
            'weeks' => $this->getCalendarWeeks(),
            'selectedDateGroups' => $this->getSelectedDateGroups(),
            'searchResults' => $this->activeTab === 'list' ? $this->getSearchResults() : null,
        ]);
    }
}
