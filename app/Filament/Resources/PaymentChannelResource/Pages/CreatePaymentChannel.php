<?php

namespace App\Filament\Resources\PaymentChannelResource\Pages;

use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\PaymentChannelResource;
use App\Services\PaymentChannelService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

class CreatePaymentChannel extends CreateRecord
{
    protected static string $resource = PaymentChannelResource::class;

    /**
     * Simpan lewat service: validasi spesifik jenis (bank wajib nomor
     * rekening & nama bank) dan otorisasi Admin/Owner dijaga di sana.
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(PaymentChannelService::class)->create($data, auth()->user());
        } catch (BusinessRuleException|AuthorizationException $e) {
            Notification::make()->danger()->title('Channel gagal dibuat')->body($e->getMessage())->send();
            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
