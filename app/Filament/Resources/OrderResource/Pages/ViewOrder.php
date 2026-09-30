<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Filament\Actions;
use Filament\Exceptions\Halt;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('koreksiTotal')
                ->label('Koreksi Total')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->form([
                    Forms\Components\TextInput::make('total_terkoreksi')
                        ->label('Total Terkoreksi')
                        ->numeric()
                        ->prefix('Rp')
                        ->required()
                        ->minValue(0)
                        ->default(fn (Order $record) => $record->total()),
                    Forms\Components\Textarea::make('alasan')
                        ->label('Alasan Koreksi')
                        ->required()
                        ->maxLength(500),
                ])
                ->action(function (array $data, Order $record): void {
                    try {
                        $totalBaru = (float) $data['total_terkoreksi'];
                        $alasan = $data['alasan'];

                        app(OrderService::class)->koreksiTotal(
                            $record,
                            $totalBaru,
                            $alasan,
                            auth()->user()
                        );

                        Notification::make()
                            ->success()
                            ->title('Total berhasil dikoreksi')
                            ->body("Total lama: Rp".number_format($record->total(), 0, ',', '.')." → Total baru: Rp".number_format($totalBaru, 0, ',', '.'))
                            ->send();

                        $record->refresh();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->danger()
                            ->title('Gagal mengkoreksi total')
                            ->body($e->getMessage())
                            ->send();

                        throw new Halt();
                    }
                }),

            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
