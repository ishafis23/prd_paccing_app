<?php

namespace App\Filament\Resources;

use App\Enums\CustomerArea;
use App\Enums\CustomerJenis;
use App\Enums\CustomerStatus;
use App\Enums\CustomerTipe;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
use App\Enums\OrderStatus;
use App\Services\CustomerPortalService;
use App\Services\ServiceReminderService;
use App\Support\EnumOptions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;

class CustomerResource extends BaseResource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Customer & Order';

    protected static ?string $navigationLabel = 'Data Customer';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nama')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('jenis')
                    ->options(EnumOptions::for(CustomerJenis::class))
                    ->default(CustomerJenis::Perorangan->value)
                    ->required(),
                Forms\Components\TextInput::make('no_hp')
                    ->label('No. HP/WA')
                    ->required()
                    ->maxLength(20),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->maxLength(255),
                Forms\Components\Textarea::make('alamat')
                    ->label('Alamat Utama')
                    ->helperText('Opsional — utk satu alamat utama saja. Multi-alamat (rumah, usaha, dst, masing-masing dgn peta sendiri) dikelola lewat tab "Alamat" di halaman edit customer.')
                    ->columnSpanFull(),
                Forms\Components\Select::make('area')
                    ->options(EnumOptions::for(CustomerArea::class))
                    ->required(),
                Forms\Components\Select::make('sumber_lead')
                    ->label('Sumber Lead')
                    ->options(EnumOptions::for(LeadSource::class))
                    ->required(),
                Forms\Components\Select::make('status')
                    ->options(EnumOptions::for(CustomerStatus::class))
                    ->default(CustomerStatus::Lead->value)
                    ->required(),
                Forms\Components\Select::make('tipe_customer')
                    ->label('Customer Baru / Lama')
                    ->options(self::opsiTipe())
                    ->default(CustomerTipe::Baru->value)
                    ->required()
                    ->helperText('Customer lama = sudah pernah order sebelum sistem web ini dipakai.'),
                Forms\Components\Textarea::make('catatan')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('wa_group_id')
                    ->label('ID Grup WA')
                    ->helperText('Salin dari menu WhatsApp → Cek ID Group WA. Isi jika dokumentasi pengerjaan customer ini juga perlu dikirim ke grup WA bersama.')
                    ->maxLength(100)
                    ->columnSpanFull(),
            ]);
    }

    /** @return array<string, string> */
    public static function opsiTipe(): array
    {
        return [
            CustomerTipe::Baru->value => 'Customer Baru',
            CustomerTipe::Lama->value => 'Customer Lama',
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['orderTerakhir.serviceCatalog', 'orderTerakhir.orderItems', 'reminderTerbaru']))
            ->columns([
                Tables\Columns\TextColumn::make('nama')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('jenis')->badge()->sortable(),
                Tables\Columns\TextColumn::make('no_hp')->label('No. HP/WA')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('area')->badge()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable()->color(fn (CustomerStatus $state): string => match ($state) {
                    CustomerStatus::Aktif => 'success',
                    CustomerStatus::Lead => 'warning',
                    CustomerStatus::Nonaktif => 'gray',
                }),
                Tables\Columns\TextColumn::make('tipe_customer')->label('Baru/Lama')->badge()->sortable()
                    ->formatStateUsing(fn (CustomerTipe $state): string => $state === CustomerTipe::Lama ? 'Lama' : 'Baru')
                    ->color(fn (CustomerTipe $state): string => $state === CustomerTipe::Lama ? 'info' : 'success'),
                Tables\Columns\TextColumn::make('orderTerakhir.tanggal_jadwal')->label('Cuci Terakhir')
                    ->date('d M Y')->placeholder('Belum pernah'),
                Tables\Columns\TextColumn::make('layanan_terakhir')->label('Layanan Terakhir')
                    ->state(fn (Customer $record): ?string => $record->orderTerakhir?->ringkasanLayanan())
                    ->placeholder('-')->wrap(),
                Tables\Columns\TextColumn::make('orderTerakhir.keluhan')->label('Keluhan Terakhir')
                    ->limit(40)->tooltip(fn (Customer $record): ?string => $record->orderTerakhir?->keluhan)
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('follow_up')->label('Follow-up')
                    ->state(function (Customer $record): string {
                        $r = $record->reminderTerbaru;

                        if ($r === null) {
                            return $record->orderTerakhir ? 'Belum dijadwalkan' : '-';
                        }

                        return sprintf('%s · terkirim %dx', $r->tanggal_servis_berikutnya?->format('d M Y'), $r->jumlah_terkirim);
                    })
                    ->description(fn (Customer $record): ?string => $record->reminderTerbaru?->terkirim_at
                        ? 'Terakhir dikirim '.$record->reminderTerbaru->terkirim_at->format('d M Y')
                        : null),
                Tables\Columns\TextColumn::make('sumber_lead')->label('Sumber Lead')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('akses_portal')
                    ->label('Portal')
                    ->boolean()
                    ->state(fn (Customer $record): bool => $record->bisaLoginPortal())
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')->dateTime('d M Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('jenis')->options(EnumOptions::for(CustomerJenis::class)),
                Tables\Filters\SelectFilter::make('area')->options(EnumOptions::for(CustomerArea::class)),
                Tables\Filters\SelectFilter::make('status')->options(EnumOptions::for(CustomerStatus::class)),
                Tables\Filters\SelectFilter::make('tipe_customer')->label('Baru/Lama')->options(self::opsiTipe()),
                Tables\Filters\Filter::make('perlu_followup')
                    ->label('Perlu follow-up (cuci terakhir ≥ 3 bulan)')
                    ->query(fn ($query) => $query
                        ->whereHas('orders', fn ($q) => $q->where('status', OrderStatus::Selesai->value))
                        ->whereDoesntHave('orders', fn ($q) => $q->where('status', OrderStatus::Selesai->value)
                            ->where('tanggal_jadwal', '>', now()->subMonthsNoOverflow(3)->toDateString()))),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('kirimPengingat')
                    ->label('Kirim Pengingat Cuci')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn (Customer $record) => $record->orderTerakhir !== null
                        && auth()->user()->hasAnyRole([RoleName::Admin->value, RoleName::Owner->value]))
                    ->requiresConfirmation()
                    ->modalDescription(fn (Customer $record) => 'Kirim WhatsApp pengingat cuci/maintenance ke '.$record->nama.' ('.$record->no_hp.') sekarang.')
                    ->action(function (Customer $record) {
                        try {
                            $out = app(ServiceReminderService::class)->kirimSekarangUntukCustomer($record);
                        } catch (BusinessRuleException $e) {
                            Notification::make()->warning()->title('Tidak bisa dikirim')->body($e->getMessage())->send();

                            return;
                        }

                        Notification::make()
                            ->title(match ($out['hasil']) {
                                'terkirim' => 'Pengingat terkirim ke '.$record->nama,
                                'dilewati' => 'Belum bisa dikirim: '.$out['reminder']->error_terakhir,
                                default => 'Gagal mengirim: '.$out['reminder']->error_terakhir,
                            })
                            ->color($out['hasil'] === 'terkirim' ? 'success' : 'warning')
                            ->send();
                    }),

                Tables\Actions\Action::make('chatWa')
                    ->label('Chat WA')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('gray')
                    ->visible(fn (Customer $record) => $record->waUrl() !== null)
                    ->url(fn (Customer $record) => $record->waUrl(), shouldOpenInNewTab: true),

                Tables\Actions\Action::make('tandaiTipe')
                    ->label(fn (Customer $record) => $record->isLama() ? 'Jadikan Customer Baru' : 'Tandai Customer Lama')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('gray')
                    ->visible(fn () => auth()->user()->hasAnyRole([RoleName::Admin->value, RoleName::Owner->value]))
                    ->requiresConfirmation()
                    ->action(function (Customer $record) {
                        $record->tandaiTipe($record->isLama() ? CustomerTipe::Baru : CustomerTipe::Lama, auth()->user());
                        Notification::make()->success()->title('Penanda diperbarui: customer '.$record->fresh()->tipe_customer->value)->send();
                    }),

                Tables\Actions\Action::make('aturPasswordPortal')
                    ->label('Atur Akses Portal')
                    ->icon('heroicon-o-key')
                    ->color('gray')
                    ->visible(fn (Customer $record) => $record->isCompany()
                        && auth()->user()->hasAnyRole([RoleName::Admin->value, RoleName::Owner->value]))
                    ->modalHeading('Atur Akses Portal Customer')
                    ->modalDescription('Portal Corporate (dev-plan/22) — khusus customer jenis Instansi/Company. Customer login pakai username (atau email) + password yang diatur di sini.')
                    ->form([
                        Forms\Components\TextInput::make('username')
                            ->label('Username')
                            ->helperText('Opsional. Bila dikosongkan, email customer dipakai untuk login.')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('password')
                            ->label('Password Baru')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8),
                    ])
                    ->action(function (Customer $record, array $data) {
                        try {
                            app(CustomerPortalService::class)->aturAkses(
                                $record,
                                $data['username'] ?? null,
                                $data['password'],
                                auth()->user(),
                            );
                            Notification::make()->success()->title('Akses Portal diatur')->send();
                        } catch (BusinessRuleException $e) {
                            Notification::make()->danger()->title('Gagal mengatur akses')->body($e->getMessage())->send();
                        }
                    }),

                Tables\Actions\Action::make('cabutAksesPortal')
                    ->label('Cabut Akses Portal')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->visible(fn (Customer $record) => $record->isCompany() && $record->bisaLoginPortal()
                        && auth()->user()->hasAnyRole([RoleName::Admin->value, RoleName::Owner->value]))
                    ->requiresConfirmation()
                    ->modalDescription('Customer tidak akan bisa login ke Portal lagi sampai diaktifkan ulang. Data customer tidak dihapus.')
                    ->action(function (Customer $record) {
                        app(CustomerPortalService::class)->cabutAkses($record, auth()->user());
                        Notification::make()->success()->title('Akses Portal dicabut')->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('tandaiLama')
                        ->label('Tandai Customer Lama')
                        ->icon('heroicon-o-clock')
                        ->visible(fn () => auth()->user()->hasAnyRole([RoleName::Admin->value, RoleName::Owner->value]))
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (\Illuminate\Support\Collection $records) {
                            $records->each(fn (Customer $c) => $c->tandaiTipe(CustomerTipe::Lama, auth()->user()));
                            Notification::make()->success()->title($records->count().' customer ditandai lama')->send();
                        }),
                    Tables\Actions\BulkAction::make('tandaiBaru')
                        ->label('Tandai Customer Baru')
                        ->icon('heroicon-o-sparkles')
                        ->visible(fn () => auth()->user()->hasAnyRole([RoleName::Admin->value, RoleName::Owner->value]))
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (\Illuminate\Support\Collection $records) {
                            $records->each(fn (Customer $c) => $c->tandaiTipe(CustomerTipe::Baru, auth()->user()));
                            Notification::make()->success()->title($records->count().' customer ditandai baru')->send();
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            CustomerResource\RelationManagers\AddressesRelationManager::class,
            CustomerResource\RelationManagers\AcUnitsRelationManager::class,
            CustomerResource\RelationManagers\RiwayatPelayananRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'view' => Pages\ViewCustomer::route('/{record}'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
