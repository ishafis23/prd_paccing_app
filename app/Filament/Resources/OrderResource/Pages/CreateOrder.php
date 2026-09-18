<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Services\CustomerService;
use App\Services\OrderService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            // dev-plan/16: mode "Pelanggan Baru" — buat customer (+ alamat +
            // unit AC pertama) dulu, lalu suntik id-nya ke payload order;
            // OrderService::createOrder() sendiri TIDAK berubah sama sekali.
            if (($data['mode_pelanggan'] ?? 'terdaftar') === 'baru') {
                $customer = app(CustomerService::class)->create([
                    'nama' => $data['pelanggan_baru_nama'] ?? null,
                    'no_hp' => $data['pelanggan_baru_no_hp'] ?? null,
                    'jenis' => $data['pelanggan_baru_jenis'] ?? null,
                    'area' => $data['pelanggan_baru_area'] ?? null,
                    'sumber_lead' => $data['pelanggan_baru_sumber_lead'] ?? null,
                    'email' => $data['pelanggan_baru_email'] ?? null,
                    'alamat_pengerjaan' => $data['alamat_pengerjaan'] ?? null,
                    'kode_ruangan' => $data['pelanggan_baru_kode_ruangan'] ?? null,
                    'jenis_unit' => $data['pelanggan_baru_jenis_unit'] ?? null,
                    'pk' => $data['pelanggan_baru_pk'] ?? null,
                ], auth()->user());

                $data['customer_id'] = $customer->id;
                $data['customer_address_id'] = $customer->alamatUtama()?->id;
                $data['customer_ac_unit_id'] = $customer->acUnits()->first()?->id;
            }

            return app(OrderService::class)->createOrder($data, auth()->user());
        } catch (BusinessRuleException|AuthorizationException $e) {
            Notification::make()->danger()->title('Order gagal dibuat')->body($e->getMessage())->send();
            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
