<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class WidenSpkRecommendationDecimalPrecision extends Migration
{
    public function up(): void
    {
        $this->changeRecommendationColumns('12,4');
    }

    public function down(): void
    {
        $this->changeRecommendationColumns('12,2');
    }

    private function changeRecommendationColumns(string $constraint): void
    {
        $fields = [];

        foreach (['current_stock_qty', 'required_qty', 'system_recommended_qty', 'recommended_qty'] as $column) {
            $fields[$column] = [
                'name'       => $column,
                'type'       => 'DECIMAL',
                'constraint' => $constraint,
                'null'       => false,
                'default'    => 0,
            ];
        }

        $this->forge->modifyColumn('spk_recommendations', $fields);
    }
}
