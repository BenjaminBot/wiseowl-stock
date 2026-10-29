<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE stock_balances ADD CONSTRAINT stock_balance_quantities CHECK (quantity >= 0 AND reserved_quantity >= 0 AND reserved_quantity <= quantity)');
        DB::statement('ALTER TABLE receipt_lines ADD CONSTRAINT receipt_values CHECK (quantity > 0 AND cost_input >= 0 AND unit_net_cost >= 0 AND selling_price >= 0)');
        DB::statement('ALTER TABLE tax_categories ADD CONSTRAINT tax_rate_range CHECK (rate >= 0 AND rate <= 100)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tax_categories DROP CONSTRAINT tax_rate_range');
        DB::statement('ALTER TABLE receipt_lines DROP CONSTRAINT receipt_values');
        DB::statement('ALTER TABLE stock_balances DROP CONSTRAINT stock_balance_quantities');
    }
};
