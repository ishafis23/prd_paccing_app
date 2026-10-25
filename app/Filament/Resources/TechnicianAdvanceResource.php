<?php

namespace App\Filament\Resources;

use App\Enums\AdvanceStatus;
use App\Enums\RoleName;
use App\Filament\Resources\TechnicianAdvanceResource\Pages;
use App\Exceptions\BusinessRuleException;
use App\Models\TechnicianAdvance;
use App\Models\User;
use App\Services\PayrollService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * SDM/HRD → "Kasbon" (dev-plan/28): admin mencatat kasbon teknisi; nominalnya
 * dipotong otomatis saat gaji dihitung (PayrollService), sisa terbawa ke
 * periode berikutnya sampai lunas.
 */
class TechnicianAdvanceResource extends BaseResource
{
    protected static ?string $model = TechnicianAdvance::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'SDM/HRD';

    protected static ?string $navigationLabel = 'Kasbon';

    protected static ?string $modelLabel = 'Kasbon';

    protected static ?string $pluralModelLabel = 'Kasbon';

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
                ->required(),
            Forms\Components\DatePicker::make('tanggal')
                ->label('Tanggal Kasbon')
                ->default(now())
                ->helperText('Kasbon dipotong pada periode gaji yang memuat tanggal ini.')
                ->required(),
            Forms\Components\TextInput::make('nominal')
                ->label('Nominal Kasbon')
                ->numeric()
                ->prefix('Rp')
                ->minValue(1)
                ->required(),
            Forms\Components\TextInput::make('cicilan_per_periode')
                ->label('Cicilan per Periode Gaji')
                ->numeric()
                ->prefix('Rp')
                ->minValue(1)
                ->helperText('Kosongkan bila dipotong lunas sekaligus di gaji berikutnya.'),
            Forms\Components\Textarea::make('keterangan')
                ->rows(2)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Teknisi')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('tanggal')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('periode_gaji')
                    ->label('Periode Gaji')
                    ->state(function (TechnicianAdvance $r): ?string {
                        $p = app(PayrollService::class)->periodeKasbon($r);

                        return $p === null ? null : $p[0]->format('d M Y').' – '.$p[1]->format('d M Y');
                    })
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('nominal')->money('IDR')->sortable(),
                Tables\Columns\TextColumn::make('cicilan_per_periode')->label('Cicilan/Periode')->money('IDR')->placeholder('Lunas sekaligus'),
                Tables\Columns\TextColumn::make('terpotong')
                    ->label('Terpotong')
                    ->state(fn (TechnicianAdvance $r): float => $r->terbayar())
                    ->money('IDR'),
                Tables\Columns\TextColumn::make('sisa')
                    ->label('Sisa')
                    ->state(fn (TechnicianAdvance $r): float => $r->sisa())
                    ->money('IDR'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (AdvanceStatus $state): string => $state->label())
                    ->color(fn (AdvanceStatus $state): string => $state === AdvanceStatus::Lunas ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('status_potong')
                    ->label('Status Potong')
                    ->badge()
                    ->state(fn (TechnicianAdvance $r): string => self::statusPotong($r))
                    ->color(fn (string $state): string => match ($state) {
                        'Sudah terpotong' => 'success',
                        'Dicicil' => 'warning',
                        default => 'danger',
                    }),
                Tables\Columns\TextColumn::make('keterangan')->limit(40)->placeholder('—')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Teknisi')
                    ->relationship('user', 'name')
                    ->searchable(),
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(AdvanceStatus::cases())->mapWithKeys(fn (AdvanceStatus $s) => [$s->value => $s->label()])->all()),
            ])
            ->actions([
                Tables\Actions\Action::make('lunasiManual')
                    ->label('Tandai sudah dibayar di periode lalu')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (TechnicianAdvance $r): bool => $r->status === AdvanceStatus::Aktif)
                    ->requiresConfirmation()
                    ->modalDescription('Mencatat pelunasan kasbon di luar perhitungan gaji. Tidak bisa dibatalkan.')
                    ->form(fn (TechnicianAdvance $r): array => [
                        Forms\Components\TextInput::make('nominal')
                            ->label('Nominal dibayar')
                            ->numeric()
                            ->prefix('Rp')
                            ->minValue(1)
                            ->default($r->sisa())
                            ->required(),
                        Forms\Components\Textarea::make('catatan')
                            ->label('Catatan')
                            ->rows(2)
                            ->required(),
                    ])
                    ->action(function (TechnicianAdvance $r, array $data): void {
                        try {
                            app(PayrollService::class)->lunasiManual($r, (float) $data['nominal'], $data['catatan'], auth()->user());
                            Notification::make()->success()->title('Pelunasan kasbon dicatat')->send();
                        } catch (BusinessRuleException|AuthorizationException $e) {
                            Notification::make()->danger()->title('Gagal mencatat pelunasan')->body($e->getMessage())->send();
                        }
                    }),
                Tables\Actions\EditAction::make()
                    ->visible(fn (TechnicianAdvance $r): bool => $r->pelunasan()->doesntExist()),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (TechnicianAdvance $r): bool => $r->pelunasan()->doesntExist()),
            ])
            ->defaultSort('tanggal', 'desc');
    }

    private static function statusPotong(TechnicianAdvance $r): string
    {
        if ($r->terbayar() > 0) {
            return $r->sisa() <= 0 ? 'Sudah terpotong' : 'Dicicil';
        }

        $lewat = app(PayrollService::class)->kasbonBelumTerpotongLewatPeriode($r->user_id)->contains('id', $r->id);

        return $lewat ? 'Belum terpotong — periode lampau' : 'Belum terpotong';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTechnicianAdvances::route('/'),
            'create' => Pages\CreateTechnicianAdvance::route('/create'),
            'edit' => Pages\EditTechnicianAdvance::route('/{record}/edit'),
        ];
    }
}
