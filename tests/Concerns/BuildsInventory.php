<?php

namespace Tests\Concerns;

use App\Models\IntakeSession;
use App\Models\Location;
use App\Models\Product;
use App\Models\SalesChannel;
use App\Models\TaxCategory;
use App\Models\User;
use Illuminate\Support\Str;

trait BuildsInventory
{
    protected function inventoryFixture(): array
    {
        $staff = User::factory()->create();
        $staff->forceFill(['is_staff' => true, 'is_manager' => true])->save();
        $location = Location::create(['name' => 'Receiving stockroom']);
        $target = Location::create(['name' => 'Fair boxes']);
        $web = SalesChannel::create(['name' => 'Website', 'type' => 'website']);
        $fair = SalesChannel::create(['name' => 'Autumn fair', 'type' => 'bookfair']);
        $unallocated = SalesChannel::create(['name' => 'Unallocated', 'type' => 'unallocated', 'is_system' => true]);
        $tax = TaxCategory::create(['name' => 'Test rate only', 'rate' => '18.0000']);
        $product = Product::create(['name' => 'Test book', 'sku' => 'BOOK-001', 'type' => 'book', 'isbn' => '0140328726']);
        $session = IntakeSession::create([
            'reference' => 'Fixture delivery', 'receiving_location_id' => $location->id, 'created_by' => $staff->id,
            'destinations' => [['sales_channel_id' => $web->id, 'location_id' => $location->id], ['sales_channel_id' => $fair->id, 'location_id' => $target->id]],
            'default_condition' => 'new', 'website_separated' => true, 'currency' => 'EUR', 'status' => 'open',
        ]);
        $input = [
            'idempotency_key' => (string) Str::uuid(), 'intake_session_id' => $session->id, 'product_id' => $product->id,
            'quantity' => 5, 'condition' => 'new', 'cost_input' => '4.5000', 'cost_basis' => 'exclusive', 'cost_mode' => 'per_copy',
            'purchase_tax_category_id' => $tax->id, 'sales_tax_category_id' => $tax->id, 'selling_price' => '8.99', 'price_confirmed' => true,
            'allocations' => [['sales_channel_id' => $web->id, 'quantity' => 2], ['sales_channel_id' => $fair->id, 'quantity' => 2], ['sales_channel_id' => $unallocated->id, 'quantity' => 1]],
        ];

        return compact('staff', 'location', 'target', 'web', 'fair', 'unallocated', 'tax', 'product', 'session', 'input');
    }
}
