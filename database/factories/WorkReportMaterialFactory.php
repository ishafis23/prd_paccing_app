<?php

namespace Database\Factories;

use App\Models\StockItem;
use App\Models\WorkReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkReportMaterial>
 */
class WorkReportMaterialFactory extends Factory
{
    public function definition(): array
    {
        return [
            'work_report_id' => WorkReport::factory(),
            'stock_item_id' => StockItem::factory(),
            'jumlah' => 1,
        ];
    }
}
