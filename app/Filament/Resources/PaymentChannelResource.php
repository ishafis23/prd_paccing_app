<?php

namespace App\Filament\Resources;

use App\Enums\PaymentChannelType;
use App\Filament\Resources\PaymentChannelResource\Pages;
use App\Models\PaymentChannel;
use App\Support\EnumOptions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Kelola master channel pembayaran (QRIS/rekening) — B15a/B16a.
 *
 * Create/Update WAJIB lewat PaymentChannelService (lihat Pages/Create* & Edit*)
 * karena validasi spesifik jenis & otorisasi dijaga di service. Finance hanya
 * melihat; Admin/Owner mengelola.
 */
class PaymentChannelResource extends Resource
{
    protected static ?string $model = PaymentChannel::class;

    protected static ?string $navigationIcon = 'heroicon-o-qr-code';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Channel Pembayaran';

    protected static ?string $modelLabel = 'Channel Pembayaran';

    protected static ?string $pluralModelLabel = 'Channel Pembayaran';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nama')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('jenis')
                    ->label('Jenis')
                    ->options(EnumOptions::for(PaymentChannelType::class))
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                        if ($state !== PaymentChannelType::Bank->value) {
                            $set('nama_bank', null);
                            $set('nomor_rekening', null);
                        }
                        if ($state !== PaymentChannelType::Qris->value) {
                            $set('gambar', null);
                        }
                    }),
                Forms\Components\TextInput::make('atas_nama')
                    ->label('Atas Nama')
                    ->maxLength(255)
                    ->helperText('Opsional — nama pemilik rekening / merchant.'),
                Forms\Components\TextInput::make('nama_bank')
                    ->label('Nama Bank')
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => $get('jenis') === PaymentChannelType::Bank->value)
                    ->required(fn (Get $get): bool => $get('jenis') === PaymentChannelType::Bank->value),
                Forms\Components\TextInput::make('nomor_rekening')
                    ->label('Nomor Rekening')
                    ->maxLength(50)
                    ->visible(fn (Get $get): bool => $get('jenis') === PaymentChannelType::Bank->value)
                    ->required(fn (Get $get): bool => $get('jenis') === PaymentChannelType::Bank->value),
                Forms\Components\FileUpload::make('gambar')
                    ->label('Gambar QRIS')
                    ->image()
                    ->disk('public')
                    ->directory('payment-channels')
                    ->maxSize(2048)
                    ->rules([
                        // B25: tolak sebelum tersimpan bila penyimpanan penuh.
                        fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                            if ($value instanceof \Illuminate\Http\UploadedFile) {
                                try {
                                    app(\App\Services\StorageQuotaService::class)->pastikanCukup($value->getSize());
                                } catch (\App\Exceptions\BusinessRuleException $e) {
                                    $fail($e->getMessage());
                                }
                            }
                        },
                    ])
                    ->helperText('PNG/JPG maks. 2MB. Ditampilkan besar di resi publik.')
                    ->visible(fn (Get $get): bool => $get('jenis') === PaymentChannelType::Qris->value),
                Forms\Components\Toggle::make('aktif')
                    ->label('Aktif')
                    ->default(true),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->terurut())
            ->columns([
                Tables\Columns\TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('jenis')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (PaymentChannelType $state): string => $state === PaymentChannelType::Qris ? 'QRIS' : 'Bank')
                    ->color(fn (PaymentChannelType $state): string => $state === PaymentChannelType::Qris ? 'info' : 'gray'),
                Tables\Columns\TextColumn::make('atas_nama')
                    ->label('Atas Nama')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('nama_bank')
                    ->label('Bank')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('nomor_rekening')
                    ->label('No. Rekening')
                    ->placeholder('—'),
                Tables\Columns\ImageColumn::make('gambar')
                    ->label('QRIS')
                    ->disk('public')
                    ->size(40)
                    ->visible(fn (?PaymentChannel $record): bool => filled($record?->gambar)),
                Tables\Columns\TextColumn::make('aktif')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Aktif' : 'Nonaktif')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('jenis')
                    ->label('Jenis')
                    ->options(EnumOptions::for(PaymentChannelType::class)),
                Tables\Filters\SelectFilter::make('aktif')
                    ->label('Status')
                    ->options([
                        '1' => 'Aktif',
                        '0' => 'Nonaktif',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentChannels::route('/'),
            'create' => Pages\CreatePaymentChannel::route('/create'),
            'edit' => Pages\EditPaymentChannel::route('/{record}/edit'),
        ];
    }
}
