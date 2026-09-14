<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Exceptions\BusinessRuleException;
use App\Models\CustomerAddress;
use App\Services\GoogleMapsLinkService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

/**
 * Tab "Alamat" di Data Customer (dev-plan/14) — sumber kebenaran alamat.
 * 1 customer → banyak alamat (rumah 1, rumah 2, usaha, dst), tiap alamat
 * punya koordinat sendiri (maps/lat/lng) karena teknisi menuju alamat
 * terpilih saat order. Unit AC customer hidup di bawah alamat ini (tab
 * "Unit AC" menunjuk ke alamat).
 */
class AddressesRelationManager extends RelationManager
{
    protected static string $relationship = 'addresses';

    protected static ?string $title = 'Alamat';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nama_lokasi')
                    ->label('Nama Lokasi')
                    ->placeholder('mis. Rumah, Rumah 2, Kantor/Usaha')
                    ->maxLength(100),
                Forms\Components\Textarea::make('alamat')
                    ->required()
                    ->rows(2)
                    ->columnSpanFull(),
                Forms\Components\Fieldset::make('Lokasi Alamat')
                    ->columnSpanFull()
                    ->schema([
                        Forms\Components\TextInput::make('maps_link')
                            ->label('Link Google Maps')
                            ->placeholder('Tempel link dari WhatsApp/Google Maps di sini')
                            ->helperText('Opsional — tempel link lalu klik "Ambil Koordinat", atau isi/geser pin manual di peta.')
                            ->dehydrated(false)
                            ->columnSpanFull()
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('ambilKoordinat')
                                    ->label('Ambil Koordinat')
                                    ->icon('heroicon-m-map-pin')
                                    ->action(function (Forms\Get $get, Forms\Set $set) {
                                        $link = trim((string) $get('maps_link'));

                                        if ($link === '') {
                                            Notification::make()
                                                ->title('Isi link Google Maps dulu.')
                                                ->warning()
                                                ->send();

                                            return;
                                        }

                                        try {
                                            $coords = app(GoogleMapsLinkService::class)->resolveCoordinates($link);
                                        } catch (BusinessRuleException $e) {
                                            Notification::make()
                                                ->title($e->getMessage())
                                                ->danger()
                                                ->send();

                                            return;
                                        }

                                        $set('latitude', $coords['lat']);
                                        $set('longitude', $coords['lng']);

                                        Notification::make()
                                            ->title('Koordinat berhasil diambil. Cek pin di peta di bawah.')
                                            ->success()
                                            ->send();
                                    })
                            ),
                        Forms\Components\TextInput::make('latitude')
                            ->numeric()
                            ->step('0.0000001')
                            ->live(onBlur: true),
                        Forms\Components\TextInput::make('longitude')
                            ->numeric()
                            ->step('0.0000001')
                            ->live(onBlur: true),
                        Forms\Components\ViewField::make('peta_lokasi')
                            ->label('Peta (klik/geser pin untuk koreksi titik)')
                            ->columnSpanFull()
                            ->dehydrated(false)
                            ->view('filament.forms.components.lokasi-picker')
                            ->viewData(function (Forms\Components\ViewField $component) {
                                $get = $component->getGetCallback();

                                return [
                                    'lat' => $get('latitude'),
                                    'lng' => $get('longitude'),
                                    'latPath' => $component->generateRelativeStatePath('latitude'),
                                    'lngPath' => $component->generateRelativeStatePath('longitude'),
                                ];
                            }),
                    ]),
                Forms\Components\Textarea::make('catatan')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama_lokasi')
            ->columns([
                Tables\Columns\TextColumn::make('nama_lokasi')
                    ->label('Nama Lokasi')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('alamat')->wrap()->searchable(),
                Tables\Columns\IconColumn::make('is_utama')
                    ->label('Utama')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-x-mark')
                    ->color(fn (bool $state): string => $state ? 'warning' : 'gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('latitude')
                    ->label('Koordinat')
                    ->formatStateUsing(fn ($record) => $record->latitude !== null ? "{$record->latitude}, {$record->longitude}" : '—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Tambah Alamat'),
            ])
            ->actions([
                Tables\Actions\Action::make('jadikanUtama')
                    ->label('Jadikan Utama')
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->visible(fn (CustomerAddress $record): bool => ! $record->is_utama)
                    ->requiresConfirmation()
                    ->action(function (CustomerAddress $record): void {
                        DB::transaction(function () use ($record): void {
                            CustomerAddress::where('customer_id', $record->customer_id)
                                ->where('id', '!=', $record->id)
                                ->update(['is_utama' => false]);
                            $record->update(['is_utama' => true]);
                        });

                        Notification::make()->success()->title('Alamat utama diganti')->send();
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}