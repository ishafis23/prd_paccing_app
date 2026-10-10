<?php

namespace App\Filament\Widgets;

use App\Services\OmsetService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tabel omset: Pekerjaan | Omset Jasa | Omset Material | Total + baris Total.
 * Toggle "rinci per transaksi" memecah baris per nama layanan. Ikut filter
 * periode dashboard; bisa diekspor CSV.
 */
class OmsetJasaMaterialWidget extends Widget
{
    use InteractsWithPageFilters;

    protected static string $view = 'filament.widgets.omset-jasa-material';

    protected static ?int $sort = 3;

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    public bool $rinci = false;

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['owner', 'admin', 'finance']) ?? false;
    }

    public function toggleRinci(): void
    {
        $this->rinci = ! $this->rinci;
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, total: array<string, mixed>, label: string, dari: string, sampai: string}
     */
    public function dataOmset(): array
    {
        $omset = app(OmsetService::class);
        [$dari, $sampai, $label] = $omset->rentangDariFilter($this->filters);
        $rows = $omset->ringkasan($dari, $sampai, $this->rinci ? 'transaksi' : 'kategori');

        return [
            'rows' => $rows,
            'total' => $omset->total($rows),
            'label' => $label,
            'dari' => $dari->toDateString(),
            'sampai' => $sampai->toDateString(),
        ];
    }

    public function exportCsv(): StreamedResponse
    {
        abort_unless(static::canView(), 403);

        $data = $this->dataOmset();
        $nama = 'omset-'.($this->rinci ? 'transaksi-' : '').$data['dari'].'_'.$data['sampai'].'.csv';

        return response()->streamDownload(function () use ($data): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Pekerjaan', 'Unit', 'Omset Jasa', 'Omset Material', 'Total']);
            foreach ($data['rows'] as $r) {
                fputcsv($out, [$r['label'], $r['unit'], $r['jasa'], $r['material'], $r['total']]);
            }
            fputcsv($out, ['Total', $data['total']['unit'], $data['total']['jasa'], $data['total']['material'], $data['total']['total']]);
            fclose($out);
        }, $nama, ['Content-Type' => 'text/csv']);
    }
}
