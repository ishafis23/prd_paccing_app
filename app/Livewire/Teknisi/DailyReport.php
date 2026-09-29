<?php

namespace App\Livewire\Teknisi;

use App\Models\DailyReport as DailyReportModel;
use App\Models\DailyWorkEntry;
use App\Models\DailyFinancialEntry;
use App\Models\ExpenseItem;
use App\Models\AdHocAcUnit;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class DailyReport extends Component
{
    // Tab control
    public string $activeTab = 'pengerjaan'; // pengerjaan | dana

    // Laporan Pengerjaan
    #[Validate('required|date|before_or_equal:today')]
    public string $tanggal = '';

    #[Validate('required|string|max:100')]
    public string $titik = '';

    #[Validate('required|string|max:150')]
    public string $customer_name = '';

    #[Validate('nullable|string|max:500')]
    public string $notes = '';

    public array $workEntries = [];

    // Laporan Dana
    #[Validate('required|integer|min:0')]
    public int $saldo_awal = 0;

    #[Validate('required|integer|min:0')]
    public int $pendapatan_customer = 0;

    #[Validate('required|in:makan,bensin,minum,lainnya')]
    public string $expense_kategori = 'makan';

    #[Validate('required|integer|min:1000|max:5000000')]
    public int $expense_nominal = 0;

    #[Validate('nullable|string|max:255')]
    public string $expense_keterangan = '';

    public array $expenses = [];

    #[Validate('nullable|integer|min:0')]
    public int $jumlah_setoran = 0;

    // Ad-Hoc AC Units
    #[Validate('required|string|max:150')]
    public string $ac_customer_name = '';

    #[Validate('required|integer|min:1')]
    public int $ac_jumlah = 1;

    #[Validate('required|in:cuci,pending')]
    public string $ac_status = 'pending';

    #[Validate('nullable|string|max:255')]
    public string $ac_catatan = '';

    public array $acUnits = [];

    public ?int $currentReportId = null;
    public bool $isSubmitting = false;

    public function mount(): void
    {
        $this->tanggal = today()->format('Y-m-d');
        $this->loadReport();
    }

    public function loadReport(): void
    {
        $teknisiId = Auth::id();
        $report = DailyReportModel::where('teknisi_id', $teknisiId)
            ->where('tanggal_laporan', $this->tanggal)
            ->first();

        if ($report) {
            $this->currentReportId = $report->id;
            $this->loadWorkEntries($report);
            $this->loadFinancialData($report);
            $this->loadAcUnits($report);
        }
    }

    private function loadWorkEntries(DailyReportModel $report): void
    {
        $this->workEntries = $report->workEntries()
            ->orderBy('urutan')
            ->get()
            ->toArray();
    }

    private function loadFinancialData(DailyReportModel $report): void
    {
        $financial = $report->financialEntry;
        if ($financial) {
            $this->saldo_awal = $financial->saldo_awal_dari_base ?? 0;
            $this->pendapatan_customer = $financial->pendapatan_dari_customer ?? 0;
            $this->jumlah_setoran = $financial->jumlah_setoran ?? 0;
            $this->expenses = $financial->expenseItems()
                ->get()
                ->toArray();
        }
    }

    private function loadAcUnits(DailyReportModel $report): void
    {
        $this->acUnits = $report->adHocAcUnits()
            ->get()
            ->toArray();
    }

    public function addWorkEntry(): void
    {
        $this->validate([
            'titik' => 'required|string|max:100',
            'customer_name' => 'required|string|max:150',
            'notes' => 'nullable|string|max:500',
        ]);

        $report = $this->getOrCreateReport();

        DailyWorkEntry::create([
            'daily_report_id' => $report->id,
            'titik' => $this->titik,
            'customer_name' => $this->customer_name,
            'notes' => $this->notes,
            'urutan' => count($this->workEntries),
        ]);

        $this->resetWorkEntry();
        $this->loadWorkEntries($report);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Titik kerja ditambahkan']);
    }

    public function deleteWorkEntry(int $id): void
    {
        DailyWorkEntry::find($id)?->delete();
        $this->loadReport();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Titik kerja dihapus']);
    }

    public function addExpense(): void
    {
        $this->validate([
            'expense_kategori' => 'required|in:makan,bensin,minum,lainnya',
            'expense_nominal' => 'required|integer|min:1000|max:5000000',
        ]);

        $report = $this->getOrCreateReport();
        $financial = $this->getOrCreateFinancialEntry($report);

        ExpenseItem::create([
            'daily_financial_entry_id' => $financial->id,
            'kategori' => $this->expense_kategori,
            'nominal' => $this->expense_nominal,
            'keterangan' => $this->expense_keterangan,
        ]);

        $this->resetExpense();
        $this->loadFinancialData($report);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Pengeluaran ditambahkan']);
    }

    public function deleteExpense(int $id): void
    {
        ExpenseItem::find($id)?->delete();
        $report = DailyReportModel::find($this->currentReportId);
        if ($report) {
            $this->loadFinancialData($report);
        }
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Pengeluaran dihapus']);
    }

    public function addAcUnit(): void
    {
        $this->validate([
            'ac_customer_name' => 'required|string|max:150',
            'ac_jumlah' => 'required|integer|min:1',
            'ac_status' => 'required|in:cuci,pending',
        ]);

        $report = $this->getOrCreateReport();

        AdHocAcUnit::create([
            'daily_report_id' => $report->id,
            'customer_name' => $this->ac_customer_name,
            'jumlah_unit' => $this->ac_jumlah,
            'status' => $this->ac_status,
            'catatan' => $this->ac_catatan,
        ]);

        $this->resetAcUnit();
        $this->loadAcUnits($report);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'AC unit ditambahkan']);
    }

    public function deleteAcUnit(int $id): void
    {
        AdHocAcUnit::find($id)?->delete();
        $report = DailyReportModel::find($this->currentReportId);
        if ($report) {
            $this->loadAcUnits($report);
        }
        $this->dispatch('notify', ['type' => 'success', 'message' => 'AC unit dihapus']);
    }

    public function submitReport(): void
    {
        $report = DailyReportModel::find($this->currentReportId);
        if (!$report) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Laporan tidak ditemukan']);
            return;
        }

        if (empty($this->workEntries)) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'Tambahkan minimal satu titik kerja']);
            return;
        }

        $this->isSubmitting = true;

        // Update financial entry dengan calculated totals
        $financial = $report->financialEntry;
        if ($financial) {
            $financial->update([
                'saldo_awal_dari_base' => $this->saldo_awal,
                'pendapatan_dari_customer' => $this->pendapatan_customer,
                'jumlah_setoran' => $this->jumlah_setoran,
            ]);
        }

        // Update report status
        $report->update(['status' => 'submitted']);

        $this->isSubmitting = false;
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Laporan berhasil disubmit']);
        $this->loadReport();
    }

    private function getOrCreateReport(): DailyReportModel
    {
        if ($this->currentReportId) {
            return DailyReportModel::find($this->currentReportId);
        }

        $report = DailyReportModel::create([
            'teknisi_id' => Auth::id(),
            'tanggal_laporan' => $this->tanggal,
            'status' => 'draft',
        ]);

        $this->currentReportId = $report->id;
        return $report;
    }

    private function getOrCreateFinancialEntry(DailyReportModel $report): DailyFinancialEntry
    {
        return $report->financialEntry ?? DailyFinancialEntry::create([
            'daily_report_id' => $report->id,
            'saldo_awal_dari_base' => 0,
            'pendapatan_dari_customer' => 0,
        ]);
    }

    private function resetWorkEntry(): void
    {
        $this->titik = '';
        $this->customer_name = '';
        $this->notes = '';
    }

    private function resetExpense(): void
    {
        $this->expense_kategori = 'makan';
        $this->expense_nominal = 0;
        $this->expense_keterangan = '';
    }

    private function resetAcUnit(): void
    {
        $this->ac_customer_name = '';
        $this->ac_jumlah = 1;
        $this->ac_status = 'pending';
        $this->ac_catatan = '';
    }

    public function render()
    {
        return view('livewire.teknisi.daily-report', [
            'totalPendapatan' => $this->saldo_awal + $this->pendapatan_customer,
            'totalPengeluaran' => collect($this->expenses)->sum('nominal') ?? 0,
            'totalSetoran' => ($this->saldo_awal + $this->pendapatan_customer) - (collect($this->expenses)->sum('nominal') ?? 0),
        ]);
    }
}
