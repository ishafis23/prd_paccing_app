<?php

namespace App\Filament\Resources;

use App\Enums\RoleName;
use App\Filament\Resources\TechnicianPayrollProfileResource\Pages;
use App\Models\TechnicianPayrollProfile;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * SDM/HRD → "Data Gaji Teknisi" (dev-plan/hrd/02): info penggajian dasar
 * per teknisi (gaji pokok, BPJS TK, potongan OFF/hari, denda telat/hari,
 * tanggal masuk sebagai dasar periode gaji).
 */
class TechnicianPayrollProfileResource extends BaseResource
{
    protected static ?string $model = TechnicianPayrollProfile::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'SDM/HRD';

    protected static ?string $navigationLabel = 'Data Gaji Teknisi';

    protected static ?string $modelLabel = 'Profil Gaji';

    protected static ?string $pluralModelLabel = 'Data Gaji Teknisi';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole([
            RoleName::Owner->value,
            RoleName::Hr->value,
            RoleName::Admin->value,
        ]) ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')
                ->label('Teknisi')
                ->options(fn () => User::role(RoleName::Teknisi->value)->pluck('name', 'id'))
                ->searchable()
                ->required()
                ->disabledOn('edit')
                ->unique(ignoreRecord: true),
            Forms\Components\DatePicker::make('tanggal_masuk')
                ->label('Tanggal Masuk')
                ->helperText('Dasar periode gaji bulanan (mis. masuk tgl 15 → periode 15 Okt – 14 Nov).')
                ->required(),
            Forms\Components\TextInput::make('cutoff_hari')
                ->label('Tanggal Cut-off Periode Gaji')
                ->numeric()
                ->minValue(1)
                ->maxValue(31)
                ->nullable()
                ->helperText('Contoh 23 → periode 24 bulan lalu s.d. 23 bulan ini. Kosongkan untuk periode 1 bulan dari tanggal acuan.'),
            Forms\Components\TextInput::make('gaji_pokok')
                ->label('Gaji Pokok')
                ->numeric()
                ->prefix('Rp')
                ->required()
                ->minValue(0),
            Forms\Components\TextInput::make('potongan_bpjs_tk')
                ->label('Potongan BPJS TK')
                ->numeric()
                ->prefix('Rp')
                ->default(129424)
                ->minValue(0),
            Forms\Components\TextInput::make('potongan_off_per_hari')
                ->label('Potongan OFF / hari')
                ->helperText('Dipakai untuk kelebihan OFF di atas kuota 5 hari.')
                ->numeric()
                ->prefix('Rp')
                ->minValue(0),
            Forms\Components\TextInput::make('denda_telat_per_hari')
                ->label('Denda Telat / hari')
                ->numeric()
                ->prefix('Rp')
                ->default(7500)
                ->minValue(0),
            Forms\Components\Toggle::make('aktif')
                ->default(true),
            Forms\Components\Textarea::make('catatan')
                ->rows(2)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Teknisi')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('tanggal_masuk')->label('Tgl Masuk')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('cutoff_hari')->label('Cut-off')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('gaji_pokok')->label('Gaji Pokok')->money('IDR')->sortable(),
                Tables\Columns\TextColumn::make('potongan_bpjs_tk')->label('BPJS TK')->money('IDR')->toggleable(),
                Tables\Columns\TextColumn::make('potongan_off_per_hari')->label('OFF/hari')->money('IDR')->toggleable(),
                Tables\Columns\TextColumn::make('denda_telat_per_hari')->label('Telat/hari')->money('IDR')->toggleable(),
                Tables\Columns\IconColumn::make('aktif')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('user_id');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTechnicianPayrollProfiles::route('/'),
            'create' => Pages\CreateTechnicianPayrollProfile::route('/create'),
            'edit' => Pages\EditTechnicianPayrollProfile::route('/{record}/edit'),
        ];
    }
}
