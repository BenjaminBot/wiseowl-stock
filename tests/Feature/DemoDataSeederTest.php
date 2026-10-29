<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockLedgerEntry;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_staff' => true, 'is_manager' => true])->save();

        return $user;
    }

    public function test_demo_receipts_are_audited_and_reseeding_preserves_edits_and_accounts(): void
    {
        $manager = $this->manager();
        $hash = $manager->password;
        $this->seed(DemoDataSeeder::class);
        $this->assertDatabaseCount('products', 15);
        $this->assertDatabaseCount('intake_sessions', 3);
        $this->assertDatabaseCount('receipt_lines', 17);
        $this->assertDatabaseCount('stock_balances', 62);
        $this->assertSame(136, StockBalance::sum('quantity'));
        $this->assertSame(136, StockLedgerEntry::sum('quantity_delta'));
        Product::where('sku', 'DEMO-002')->firstOrFail()->update(['name' => 'Staff edited demo title']);
        $this->seed(DemoDataSeeder::class);
        $this->assertDatabaseCount('receipt_lines', 17);
        $this->assertDatabaseCount('products', 15);
        $this->assertDatabaseCount('users', 1);
        $this->assertSame($hash, $manager->fresh()->password);
        $this->assertSame(136, StockBalance::sum('quantity'));
        $this->assertDatabaseHas('products', ['sku' => 'DEMO-002', 'name' => 'Staff edited demo title']);
    }

    public function test_existing_real_isbn_is_not_overwritten_or_duplicated(): void
    {
        $this->manager();
        Product::create(['sku' => 'REAL-001', 'name' => 'Existing stock title', 'isbn' => '9780140328721', 'type' => 'book']);
        $this->seed(DemoDataSeeder::class);
        $this->assertDatabaseHas('products', ['sku' => 'REAL-001', 'name' => 'Existing stock title', 'isbn' => '9780140328721']);
        $this->assertDatabaseHas('products', ['sku' => 'DEMO-001', 'isbn' => null]);
        $this->assertDatabaseCount('products', 16);
    }

    public function test_demo_seed_refuses_production_before_writing(): void
    {
        $this->app->instance('env', 'production');
        try {
            app(DemoDataSeeder::class)->run();
            $this->fail('Demo seeder ran in production.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('local or testing', $exception->getMessage());
            $this->assertDatabaseCount('products', 0);
        }
    }
}
