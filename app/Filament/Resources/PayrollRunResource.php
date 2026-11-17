<?php

namespace App\Filament\Resources;

use App\Enums\PayrollItemJenis;
use App\Enums\PayrollStatus;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\PayrollRunResource\Pages;
use App\Models\PayrollRun;
use App\Models\TechnicianPayrollProfile;
use App\Services\PayrollService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;

/**
 * SDM/HRD → "Penggajian" (dev-plan/hrd/02): hitung gaji teknisi per periode,
 * sesuaikan Bonus Umum, ACC, tandai dibayar, dan buka slip.
 */
class PayrollRunResource extends BaseResource
{
    protected static ?string $model = PayrollRun::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationGroup = 'SDM/HRD';

    protected static ?string $navigationLabel = 'Penggajian';

    protected static ?string $modelLabel = 'Gaji';

    protected static ?string $pluralModelLabel = 'Penggajian';

    public static function canViewAny(): bool
    {
        return PayrollService::boleh(auth()->user());
    }

    public static function canCreate(): bool
    {
        return false; // dibuat lewat aksi "Hitung Gaji".
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Teknisi')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('periode')
                    ->label('Periode')
                    ->state(fn (PayrollRun $r): string => $r->periode_mulai->format('d M Y').' – '.$r->periode_selesai->format('d M Y')),
                Tables\Columns\TextColumn::make('gaji_pokok')->label('Gaji Pokok')->money('IDR')->toggleable(),
                Tables\Columns\TextColumn::make('bonus_bulanan')->label('Bonus Bulanan')->money('IDR')->toggleable(),
                Tables\Columns\TextColumn::make('bonus_umum')->label('Bonus Umum')->money('IDR')->toggleable(),
                Tables\Columns\TextColumn::make('bonus_mingguan')->label('Bonus Mingguan')->money('IDR')->toggleable(),
                Tables\Columns\TextColumn::make('total_denda_telat')->label('Denda Telat')->money('IDR')->toggleable(),
                Tables\Columns\TextColumn::make('total_potongan_off')->label('Potongan OFF')->money('IDR')->toggleable(),
                Tables\Columns\TextColumn::make('total_potongan_setengah_hari')->label('Pulang Cepat')->money('IDR')->toggleable(),
                Tables\Columns\TextColumn::make('total_potongan_kasbon')->label('Kasbon')->money('IDR')->toggleable(),
                Tables\Columns\TextColumn::make('potongan_bpjs_tk')->label('BPJS TK')->money('IDR')->toggleable(),
                Tables\Columns\TextColumn::make('gaji_bersih')->label('Gaji Bersih')->money('IDR')->weight('bold')->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (PayrollStatus $state): string => match ($state) {
                        PayrollStatus::Draft => 'gray',
                        PayrollStatus::Disetujui => 'info',
                        PayrollStatus::Dibayar => 'success',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Teknisi')
                    ->relationship('user', 'name')
                    ->searchable(),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        PayrollStatus::Draft->value => 'Draft',
                        PayrollStatus::Disetujui->value => 'Disetujui',
                        PayrollStatus::Dibayar->value => 'Dibayar',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('hitungGaji')
                    ->label('Hitung Gaji')
                    ->icon('heroicon-o-calculator')
                    ->visible(fn (): bool => static::canViewAny())
                    ->form([
                        Forms\Components\Select::make('profil_id')
                            ->label('Teknisi')
                            ->options(fn () => TechnicianPayrollProfile::with('user')->get()
                                ->mapWithKeys(fn (TechnicianPayrollProfile $p) => [$p->id => $p->user?->name ?? "#{$p->user_id}"]))
                            ->searchable()
                            ->required(),
                        Forms\Components\DatePicker::make('periode_mulai')
                            ->label('Tanggal acuan periode')
                            ->helperText('Periode mengikuti tanggal cut-off teknisi (mis. cut-off 23 → 24 Sep–23 Okt). Tanpa cut-off: 1 bulan dari tanggal ini.')
                            ->required()
                            ->default(now()->startOfMonth()),
                    ])
                    ->action(function (array $data): void {
                        $profil = TechnicianPayrollProfile::findOrFail($data['profil_id']);

                        try {
                            app(PayrollService::class)->hitung($profil, Carbon::parse($data['periode_mulai']), auth()->user());
                            Notification::make()->success()->title('Gaji dihitung')->send();
                        } catch (BusinessRuleException|AuthorizationException $e) {
                            Notification::make()->danger()->title('Gagal menghitung gaji')->body($e->getMessage())->send();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('bonusUmum')
                    ->label('Bonus Umum')
                    ->icon('heroicon-o-gift')
                    ->color('gray')
                    ->visible(fn (PayrollRun $r): bool => $r->draft() && static::canViewAny())
                    ->form([
                        Forms\Components\TextInput::make('nominal')
                            ->label('Bonus Umum')
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->default(fn (PayrollRun $r) => (float) $r->bonus_umum),
                    ])
                    ->action(function (PayrollRun $record, array $data): void {
                        try {
                            app(PayrollService::class)->setBonusUmum($record, (float) $data['nominal'], auth()->user());
                            Notification::make()->success()->title('Bonus umum diperbarui')->send();
                        } catch (BusinessRuleException|AuthorizationException $e) {
                            Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send();
                        }
                    }),
                Tables\Actions\Action::make('hitungUlang')
                    ->label('Hitung Ulang')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (PayrollRun $r): bool => $r->draft() && static::canViewAny())
                    ->requiresConfirmation()
                    ->action(function (PayrollRun $record): void {
                        try {
                            app(PayrollService::class)->hitungUlang($record, auth()->user());
                            Notification::make()->success()->title('Gaji dihitung ulang')->send();
                        } catch (BusinessRuleException|AuthorizationException $e) {
                            Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send();
                        }
                    }),
                static::aksiEditPeriode(),
                Tables\Actions\Action::make('acc')
                    ->label('ACC')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (PayrollRun $r): bool => $r->draft() && static::canViewAny())
                    ->requiresConfirmation()
                    ->action(function (PayrollRun $record): void {
                        try {
                            app(PayrollService::class)->acc($record, auth()->user());
                            Notification::make()->success()->title('Gaji disetujui')->send();
                        } catch (BusinessRuleException|AuthorizationException $e) {
                            Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send();
                        }
                    }),
                Tables\Actions\Action::make('bayar')
                    ->label('Tandai Dibayar')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (PayrollRun $r): bool => $r->status === PayrollStatus::Disetujui && static::canViewAny())
                    ->requiresConfirmation()
                    ->action(function (PayrollRun $record): void {
                        try {
                            app(PayrollService::class)->bayar($record, auth()->user());
                            Notification::make()->success()->title('Gaji ditandai dibayar')->send();
                        } catch (BusinessRuleException|AuthorizationException $e) {
                            Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send();
                        }
                    }),
                static::aksiHapus(),
                Tables\Actions\Action::make('slip')
                    ->label('Slip')
                    ->icon('heroicon-o-document-text')
                    ->visible(fn (PayrollRun $r): bool => $r->slipAktif())
                    ->url(fn (PayrollRun $record): string => route('payroll.slip', $record))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('periode_selesai', 'desc');
    }

    /**
     * Revisi 17 Nov 2026 poin 28: ubah tanggal mulai/selesai periode (draft).
     */
    public static function aksiEditPeriode(string $kelas = Tables\Actions\Action::class)
    {
        return $kelas::make('editPeriode')
            ->label('Edit Periode')
            ->icon('heroicon-o-calendar-days')
            ->color('gray')
            ->visible(fn (PayrollRun $r): bool => $r->draft() && static::canViewAny())
            ->modalHeading('Edit Periode Gaji')
            ->modalDescription('Gaji dihitung ulang untuk periode baru. Bonus/potongan lain yang sudah diinput tetap ikut.')
            ->fillForm(fn (PayrollRun $r): array => [
                'periode_mulai' => $r->periode_mulai->toDateString(),
                'periode_selesai' => $r->periode_selesai->toDateString(),
            ])
            ->form([
                Forms\Components\DatePicker::make('periode_mulai')->label('Mulai tanggal')->required(),
                Forms\Components\DatePicker::make('periode_selesai')->label('Sampai tanggal')->required()->afterOrEqual('periode_mulai'),
            ])
            ->action(function (PayrollRun $record, array $data): void {
                try {
                    app(PayrollService::class)->ubahPeriode(
                        $record,
                        Carbon::parse($data['periode_mulai']),
                        Carbon::parse($data['periode_selesai']),
                        auth()->user()
                    );
                    Notification::make()->success()->title('Periode gaji diperbarui')->send();
                } catch (BusinessRuleException|AuthorizationException $e) {
                    Notification::make()->danger()->title('Gagal mengubah periode')->body($e->getMessage())->send();
                }
            });
    }

    /**
     * Revisi 17 Nov 2026 poin 28: hapus gaji draft yang salah input.
     */
    public static function aksiHapus(string $kelas = Tables\Actions\Action::class)
    {
        return $kelas::make('hapusGaji')
            ->label('Hapus')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->visible(fn (PayrollRun $r): bool => $r->draft() && static::canViewAny())
            ->requiresConfirmation()
            ->modalHeading('Hapus gaji draft?')
            ->modalDescription('Gaji draft ini (termasuk bonus/potongan lain yang diinput) dihapus permanen. Gaji yang sudah di-ACC tidak bisa dihapus.')
            ->action(function (PayrollRun $record): void {
                try {
                    app(PayrollService::class)->hapus($record, auth()->user());
                    Notification::make()->success()->title('Gaji dihapus')->send();
                } catch (BusinessRuleException|AuthorizationException $e) {
                    Notification::make()->danger()->title('Gagal menghapus')->body($e->getMessage())->send();
                }
            });
    }

    /**
     * Revisi 17 Nov 2026 poin 29: tambah bonus / potongan lain (boleh berkali-kali).
     */
    public static function aksiPenyesuaian(PayrollItemJenis $jenis, string $kelas = Tables\Actions\Action::class)
    {
        $bonus = $jenis === PayrollItemJenis::Bonus;

        return $kelas::make($bonus ? 'tambahBonus' : 'tambahPotongan')
            ->label($bonus ? '+ Input Bonus' : '+ Input Potongan Lain')
            ->icon($bonus ? 'heroicon-o-plus-circle' : 'heroicon-o-minus-circle')
            ->color($bonus ? 'success' : 'danger')
            ->visible(fn (PayrollRun $r): bool => $r->draft() && static::canViewAny())
            ->modalHeading($bonus ? 'Input Bonus' : 'Input Potongan Lain')
            ->form([
                Forms\Components\TextInput::make('keterangan')
                    ->label($bonus ? 'Bonus untuk' : 'Potongan untuk')
                    ->placeholder($bonus ? 'mis. Game 5' : 'mis. ganti pipa')
                    ->datalist($bonus ? ['Game 4', 'Game 5', 'Game 6'] : [])
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('nominal')
                    ->numeric()
                    ->prefix('Rp')
                    ->minValue(1)
                    ->required(),
                Forms\Components\DatePicker::make('tanggal')
                    ->label('Tanggal kejadian (opsional)')
                    ->default(now()),
                Forms\Components\Textarea::make('catatan')
                    ->label('Catatan / detail')
                    ->rows(2),
            ])
            ->action(function (PayrollRun $record, array $data) use ($jenis, $bonus): void {
                try {
                    app(PayrollService::class)->tambahPenyesuaian(
                        $record,
                        $jenis,
                        $data['keterangan'],
                        (float) $data['nominal'],
                        auth()->user(),
                        $data['tanggal'] ?? null,
                        $data['catatan'] ?? null,
                    );
                    Notification::make()->success()->title($bonus ? 'Bonus ditambahkan' : 'Potongan ditambahkan')->send();
                } catch (BusinessRuleException|AuthorizationException $e) {
                    Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send();
                }
            });
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Ringkasan')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('user.name')->label('Teknisi'),
                    Infolists\Components\TextEntry::make('status')->badge(),
                    Infolists\Components\TextEntry::make('periode')
                        ->state(fn (PayrollRun $r): string => $r->periode_mulai->format('d M Y').' – '.$r->periode_selesai->format('d M Y')),
                ]),
            Infolists\Components\Section::make('Bonus & Potongan Lain (input admin)')
                ->description('Gunakan tombol + Input Bonus / + Input Potongan Lain di kanan atas. Bisa lebih dari satu.')
                ->schema([
                    Infolists\Components\ViewEntry::make('penyesuaian')
                        ->hiddenLabel()
                        ->view('payroll._penyesuaian')
                        ->columnSpanFull(),
                ]),
            Infolists\Components\Section::make('Rincian Gaji')
                ->schema([
                    Infolists\Components\ViewEntry::make('rincian')
                        ->hiddenLabel()
                        ->view('payroll._rincian')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayrollRuns::route('/'),
            'view' => Pages\ViewPayrollRun::route('/{record}'),
        ];
    }
}
