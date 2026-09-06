<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\UserResource;
use App\Services\UserService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(UserService::class)->updateUser($record, $data, auth()->user());
        } catch (BusinessRuleException|AuthorizationException $e) {
            Notification::make()->danger()->title('Perubahan gagal disimpan')->body($e->getMessage())->send();
            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
