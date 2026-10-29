<?php

namespace Tests\Feature\Inventory;

use App\Models\StockBalance;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Symfony\Component\Process\Process;
use Tests\Concerns\BuildsInventory;
use Tests\TestCase;

class ReceiveStockConcurrencyTest extends TestCase
{
    use BuildsInventory, DatabaseMigrations;

    public function test_concurrent_retries_post_one_receipt_on_postgresql(): void
    {
        $this->assertSame('pgsql', config('database.default'));
        $this->assertSame('wise_owl_testing', config('database.connections.pgsql.database'));
        $f = $this->inventoryFixture();
        $command = [PHP_BINARY, base_path('tests/Support/receive-worker.php'), (string) $f['staff']->id, base64_encode(json_encode($f['input'], JSON_THROW_ON_ERROR))];
        $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => 'wise_owl_testing'];
        $first = new Process($command, base_path(), $env, timeout: 30);
        $second = new Process($command, base_path(), $env, timeout: 30);
        $first->start();
        $second->start();
        $first->wait();
        $second->wait();
        $this->assertTrue($first->isSuccessful(), $first->getErrorOutput().$first->getOutput());
        $this->assertTrue($second->isSuccessful(), $second->getErrorOutput().$second->getOutput());
        $this->assertSame(trim($first->getOutput()), trim($second->getOutput()));
        $this->assertDatabaseCount('receipt_lines', 1);
        $this->assertDatabaseCount('stock_batches', 1);
        $this->assertSame(5, StockBalance::sum('quantity'));
    }
}
