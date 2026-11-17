<?php

namespace App\Filament\Resources;

use App\Enums\RoleName;
use App\Filament\Resources\TechnicianOffDayResource\Pages;
use App\Models\TechnicianOffDay;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * SDM/HRD → "Jadwal OFF" (dev-plan/hrd/02): admin menandai tanggal libur
 * teknisi. Kelebihan di atas kuota 5 hari/periode dipotong saat penggajian.
 */
class TechnicianOffDayResource extends BaseResource
{
    protected static ?string $model = TechnicianOffDay::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'SDM/HRD';

    protected static ?string $navigationLabel = 'Jadwal OFF';

    protected static ?string $modelLabel = 'Hari OFF';

    protected static ?string $pluralModelLabel = 'Jadwal OFF';

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
                ->required(),
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
                Tables\Columns\TextColumn::make('tanggal')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('catatan')->limit(40)->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Teknisi')
                    ->relationship('user', 'name')
                    ->searchable(),
                // Revisi 17 Nov 2026 poin 27: filter periode (mulai tgl … sampai tgl …).
                Tables\Filters\Filter::make('periode')
                    ->label('Periode')
                    ->form([
                        Forms\Components\DatePicker::make('dari')->label('Mulai tanggal'),
                        Forms\Components\DatePicker::make('sampai')->label('Sampai tanggal'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['dari'] ?? null, fn (Builder $q, $d) => $q->whereDate('tanggal', '>=', $d))
                        ->when($data['sampai'] ?? null, fn (Builder $q, $d) => $q->whereDate('tanggal', '<=', $d)))
                    ->indicateUsing(function (array $data): array {
                        $out = [];
                        if ($data['dari'] ?? null) {
                            $out[] = Tables\Filters\Indicator::make('Mulai '.Carbon::parse($data['dari'])->format('d M Y'))->removeField('dari');
                        }
                        if ($data['sampai'] ?? null) {
                            $out[] = Tables\Filters\Indicator::make('Sampai '.Carbon::parse($data['sampai'])->format('d M Y'))->removeField('sampai');
                        }

                        return $out;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('tanggal', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTechnicianOffDays::route('/'),
            'create' => Pages\CreateTechnicianOffDay::route('/create'),
            'edit' => Pages\EditTechnicianOffDay::route('/{record}/edit'),
        ];
    }
}
