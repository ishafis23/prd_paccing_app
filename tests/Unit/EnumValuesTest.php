<?php

namespace Tests\Unit;

use App\Enums\CustomerArea;
use App\Enums\OrderStatus;
use App\Enums\RoleName;
use PHPUnit\Framework\TestCase;

class EnumValuesTest extends TestCase
{
    public function test_role_names_have_expected_values(): void
    {
        $this->assertSame('owner', RoleName::Owner->value);
        $this->assertSame('teknisi', RoleName::Teknisi->value);
        $this->assertSame(['owner', 'admin', 'finance', 'hr', 'teknisi'], array_column(RoleName::cases(), 'value'));
    }

    public function test_order_statuses_cover_full_workflow(): void
    {
        $expected = ['baru', 'terjadwal', 'menuju_lokasi', 'dikerjakan', 'selesai', 'butuh_followup', 'batal'];
        $this->assertSame($expected, array_column(OrderStatus::cases(), 'value'));
    }

    public function test_customer_areas_match_business_regions(): void
    {
        $this->assertSame(['makassar', 'gowa', 'maros'], array_column(CustomerArea::cases(), 'value'));
    }

    public function test_all_enum_values_are_unique_within_each_enum(): void
    {
        $enums = [
            RoleName::cases(),
            OrderStatus::cases(),
            CustomerArea::cases(),
        ];

        foreach ($enums as $cases) {
            $values = array_column($cases, 'value');
            $this->assertSame($values, array_unique($values));
        }
    }
}
