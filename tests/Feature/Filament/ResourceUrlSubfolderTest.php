<?php

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Facades\Filament;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('URL resource memakai APP_URL eksplisit, bukan root request ambient (regresi subfolder — redirect setelah tambah orderan)', function () {
    config(['app.url' => 'https://mycompany.web.id/paccing/public']);

    expect(OrderResource::getUrl('index'))
        ->toBe('https://mycompany.web.id/paccing/public/admin/orders');
});

it('URL resource dengan record ikut APP_URL eksplisit', function () {
    config(['app.url' => 'https://mycompany.web.id/public']);

    $order = Order::factory()->create();

    expect(OrderResource::getUrl('view', ['record' => $order]))
        ->toBe('https://mycompany.web.id/public/admin/orders/'.$order->id);
});

it('URL resource default (tanpa tenancy) tidak mengirim query string tenant', function () {
    config(['app.url' => 'http://localhost']);

    expect(CustomerResource::getUrl('index'))
        ->toBe('http://localhost/admin/customers');
});

it('URL resource relatif dibiarkan apa adanya (isAbsolute=false)', function () {
    expect(OrderResource::getUrl('index', [], isAbsolute: false))
        ->toBe('/admin/orders');
});