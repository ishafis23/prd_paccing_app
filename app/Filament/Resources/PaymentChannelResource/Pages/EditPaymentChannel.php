<?php

namespace App\Filament\Resources\PaymentChannelResource\Pages;

use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\PaymentChannelResource;
use App\Services\PaymentChannelService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

class EditPaymentChannel extends EditRecord
{
    protected static string $resource = PaymentChannelResource::class;

    /**
     * Simpan lewat service — validasi & otorisasi dijaga di sana, bukan di form.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(PaymentChannelService::class)->update($record, $data, auth()->user());
        } catch (BusinessRuleException|AuthorizationException $e) {
            Notification::make()->danger()->title('Perubahan gagal disimpan')->body($e->getMessage())->send();
            $this->halt();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
