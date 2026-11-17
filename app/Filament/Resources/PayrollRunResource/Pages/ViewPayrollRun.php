<?php

namespace App\Filament\Resources\PayrollRunResource\Pages;

use App\Enums\PayrollItemJenis;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\PayrollRunResource;
use App\Models\PayrollRunAdjustment;
use App\Services\PayrollService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Auth\Access\AuthorizationException;

class ViewPayrollRun extends ViewRecord
{
    protected static string $resource = PayrollRunResource::class;

    protected function getHeaderActions(): array
    {
        $kelas = Actions\Action::class;
        $segarkan = fn () => $this->record->refresh();

        return [
            PayrollRunResource::aksiPenyesuaian(PayrollItemJenis::Bonus, $kelas)->record($this->record)->after($segarkan),
            PayrollRunResource::aksiPenyesuaian(PayrollItemJenis::Potongan, $kelas)->record($this->record)->after($segarkan),
            PayrollRunResource::aksiEditPeriode($kelas)->record($this->record)->after($segarkan),
            PayrollRunResource::aksiHapus($kelas)->record($this->record)
                ->after(fn () => $this->redirect(PayrollRunResource::getUrl('index'))),
        ];
    }

    public function hapusPenyesuaian(int $id): void
    {
        $adj = PayrollRunAdjustment::query()->where('payroll_run_id', $this->record->id)->findOrFail($id);

        try {
            app(PayrollService::class)->hapusPenyesuaian($adj, auth()->user());
            $this->record->refresh();
            Notification::make()->success()->title('Dihapus')->send();
        } catch (BusinessRuleException|AuthorizationException $e) {
            Notification::make()->danger()->title('Gagal menghapus')->body($e->getMessage())->send();
        }
    }
}
