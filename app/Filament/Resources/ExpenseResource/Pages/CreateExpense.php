<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Enums\ExpenseCategory;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\ExpenseResource;
use App\Services\FinanceService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

class CreateExpense extends CreateRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(FinanceService::class)->createExpense(
                ExpenseCategory::from($data['kategori']),
                (float) $data['nominal'],
                auth()->user(),
                $data['tanggal'] ?? null,
                $data['keterangan'] ?? null,
                $data['bukti'] ?? null,
            );
        } catch (BusinessRuleException|AuthorizationException $e) {
            Notification::make()->danger()->title('Gagal mencatat pengeluaran')->body($e->getMessage())->send();
            $this->halt();
        }
    }
}
