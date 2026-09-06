<?php

namespace App\Filament\Resources\PaymentChannelResource\Pages;

use App\Filament\Resources\PaymentChannelResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPaymentChannels extends ListRecords
{
    protected static string $resource = PaymentChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Buat Channel'),
        ];
    }
}
