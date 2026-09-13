<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Enums\CustomerJenis;
use App\Enums\UnitType;
use App\Models\Customer;
use App\Services\CustomerAcUnitImportService;
use App\Support\EnumOptions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Daftar Unit AC milik customer company (mis. sekolah/kantor banyak
 * unit) — dasar histori pencucian per unit & portal corporate (menyusul,
 * lihat dev-plan/12 §3.6/§3.10). Hanya tampil utk customer jenis Company.
 */
class AcUnitsRelationManager extends RelationManager
{
    protected static string $relationship = 'acUnits';

    protected static ?string $title = 'Unit AC';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Customer && $ownerRecord->jenis === CustomerJenis::Company;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('kode_unit')
                    ->label('Kode Unit')
                    ->required()
                    ->maxLength(50),
                Forms\Components\TextInput::make('kode_ruangan')
                    ->label('Ruangan/Lokasi')
                    ->required()
                    ->maxLength(100),
                Forms\Components\Select::make('jenis_unit')
                    ->label('Jenis Unit')
                    ->options(EnumOptions::for(UnitType::class)),
                Forms\Components\TextInput::make('pk')
                    ->label('Kapasitas (PK)')
                    ->placeholder('mis. 1 PK, 1.5 PK')
                    ->maxLength(20),
                Forms\Components\Textarea::make('catatan')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('kode_unit')
            ->columns([
                Tables\Columns\TextColumn::make('kode_unit')->label('Kode Unit')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('kode_ruangan')->label('Ruangan/Lokasi')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('jenis_unit')->label('Jenis Unit')->badge(),
                Tables\Columns\TextColumn::make('pk')->label('PK'),
                Tables\Columns\TextColumn::make('catatan')->limit(40)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
                Action::make('unduhTemplateUnitAc')
                    ->label('Unduh Template')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn () => app(CustomerAcUnitImportService::class)->unduhTemplate()),
                Action::make('importUnitAc')
                    ->label('Import Excel')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('primary')
                    ->modalHeading('Import Unit AC dari Excel')
                    ->modalSubmitActionLabel('Import Sekarang')
                    ->modalWidth('xl')
                    ->form([
                        Forms\Components\FileUpload::make('file')
                            ->label('File Excel / CSV')
                            ->disk('local')
                            ->directory('import-unit-ac')
                            ->maxSize(5120)
                            ->required()
                            ->helperText('.xlsx / .csv — maks. 5 MB & '.CustomerAcUnitImportService::MAX_BARIS.' baris. Data akan ditambahkan ke customer ini. Unduh Template dulu untuk format kolom yang benar.'),
                    ])
                    ->action(function (array $data): void {
                        $service = app(CustomerAcUnitImportService::class);

                        $hasil = $service->import(
                            Storage::disk('local')->path($data['file']),
                            $this->getOwnerRecord(),
                            auth()->user(),
                        );

                        Storage::disk('local')->delete($data['file']);

                        Notification::make()
                            ->title(sprintf(
                                'Import selesai: %d dibuat, %d dilewati, %d gagal',
                                $hasil['berhasil'],
                                $hasil['dilewati'],
                                $hasil['gagal']
                            ))
                            ->{$hasil['gagal'] > 0 ? 'warning' : 'success'}()
                            ->send();

                        if ($hasil['rincian'] !== []) {
                            $rincian = array_slice($hasil['rincian'], 0, 15);
                            $sisa = count($hasil['rincian']) - count($rincian);

                            if ($sisa > 0) {
                                $rincian[] = "... dan {$sisa} baris lainnya.";
                            }

                            Notification::make()
                                ->title('Rincian baris dilewati/gagal')
                                ->body(implode("\n", $rincian))
                                ->warning()
                                ->send();
                        }
                    }),
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
}
