<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\UserResource;
use App\Services\UserService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(UserService::class)->createUser($data, auth()->user());
        } catch (BusinessRuleException|AuthorizationException $e) {
            Notification::make()->danger()->title('Pengguna gagal dibuat')->body($e->getMessage())->send();
            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
