<?php

namespace App\Filament\Resources\ServiceReminderResource\Pages;

use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\ServiceReminderResource;
use App\Models\Order;
use App\Services\PaymentService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

class CreateServiceReminder extends CreateRecord
{
    protected static string $resource = ServiceReminderResource::class;

    /**
     * Simpan lewat service (B35): otorisasi Admin/Owner, larangan duplikat
     * per order, & fallback interval/tanggal dijaga di PaymentService.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $order = Order::query()->with('serviceCatalog')->findOrFail((int) $data['order_id']);

        try {
            return app(PaymentService::class)->buatReminderManual(
                $order,
                auth()->user(),
                (int) $data['interval_bulan'],
                $data['tanggal_servis_berikutnya'] ?? null,
            );
        } catch (BusinessRuleException|AuthorizationException $e) {
            Notification::make()
                ->danger()
                ->title('Notice gagal dibuat')
                ->body($e->getMessage())
                ->send();

            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
