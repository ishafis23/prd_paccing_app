<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(OrderService::class)->createOrder($data, auth()->user());
        } catch (BusinessRuleException|AuthorizationException $e) {
            Notification::make()->danger()->title('Order gagal dibuat')->body($e->getMessage())->send();
            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
