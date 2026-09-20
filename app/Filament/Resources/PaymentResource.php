<?php

namespace App\Filament\Resources;

use App\Enums\PaymentStatus;
use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use App\Support\EnumOptions;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/** Read-only — pencatatan pembayaran wajib lewat aksi "Catat Pembayaran" di OrderResource. */
class PaymentResource extends BaseResource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Finance';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('order.customer.nama')->label('Customer')->searchable(),
                Tables\Columns\TextColumn::make('metode')->badge(),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (PaymentStatus $state) => match ($state) {
                    PaymentStatus::Lunas => 'success',
                    PaymentStatus::Dp => 'warning',
                    PaymentStatus::BelumBayar => 'gray',
                }),
                Tables\Columns\TextColumn::make('total_tagihan')->money('IDR'),
                Tables\Columns\TextColumn::make('jumlah_dibayar')->money('IDR'),
                Tables\Columns\TextColumn::make('tanggal_bayar')->date('d M Y')->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(EnumOptions::for(PaymentStatus::class)),
            ])
            ->actions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
        ];
    }
}
