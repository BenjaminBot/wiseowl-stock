<?php

namespace Database\Seeders;

use App\Actions\Inventory\ReceiveStock;
use App\Models\Category;
use App\Models\IntakeSession;
use App\Models\Location;
use App\Models\Product;
use App\Models\ReceiptLine;
use App\Models\SalesChannel;
use App\Models\Supplier;
use App\Models\TaxCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use RuntimeException;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Demo data may only be seeded in local or testing environments.');
        }

        $actor = User::where('is_staff', true)->where('is_manager', true)->orderBy('id')->first();
        if (! $actor) {
            throw new RuntimeException('Create a manager with stock:create-staff before seeding demo data.');
        }

        DB::transaction(function () use ($actor) {
            // Serialises repeat/concurrent runs without modifying the account.
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $this->call(DatabaseSeeder::class);
            $unallocated = SalesChannel::where('is_system', true)->where('type', 'unallocated')->sole();
            $categories = [];
            foreach (['Picture books', 'Children\'s fiction', 'Fiction', 'Non-fiction', 'Toys', 'Stationery'] as $name) {
                $categories[$name] = Category::firstOrCreate(['name' => 'Demo: '.$name]);
            }
            $supplier = Supplier::firstOrCreate(['name' => 'Demo: Book distributor']);
            $otherSupplier = Supplier::firstOrCreate(['name' => 'Demo: Toys and gifts supplier']);
            $stockroom = Location::firstOrCreate(['name' => 'Demo: Stockroom']);
            $websiteShelf = Location::firstOrCreate(['name' => 'Demo: Website shelf']);
            $shop = Location::firstOrCreate(['name' => 'Demo: Shop']);
            $fairBoxes = Location::firstOrCreate(['name' => 'Demo: Autumn fair boxes']);
            $web = SalesChannel::firstOrCreate(['name' => 'Demo: Website'], ['type' => 'website']);
            $shopChannel = SalesChannel::firstOrCreate(['name' => 'Demo: Shop'], ['type' => 'shop']);
            $fair = SalesChannel::firstOrCreate(['name' => 'Demo: Autumn bookfair'], ['type' => 'bookfair']);
            // Arbitrary illustrative fixtures, not a statement of applicable tax treatment.
            $zero = TaxCategory::firstOrCreate(['name' => 'Demo only: 0% example'], ['rate' => '0.0000']);
            $exampleRate = TaxCategory::firstOrCreate(['name' => 'Demo only: 18% example'], ['rate' => '18.0000']);
            $destinations = [
                ['sales_channel_id' => $web->id, 'location_id' => $websiteShelf->id],
                ['sales_channel_id' => $shopChannel->id, 'location_id' => $shop->id],
                ['sales_channel_id' => $fair->id, 'location_id' => $fairBoxes->id],
            ];
            $sessions = [];
            foreach (['DEMO-DELIVERY-001', 'DEMO-DELIVERY-002', 'DEMO-NEW-INTAKE'] as $i => $reference) {
                $sessions[$i] = IntakeSession::firstOrCreate(['reference' => $reference], [
                    'supplier_id' => ($i === 1 ? $otherSupplier : $supplier)->id,
                    'receiving_location_id' => ($i === 1 ? $shop : $stockroom)->id,
                    'destinations' => $destinations, 'default_condition' => 'new',
                    'website_separated' => $i !== 1, 'currency' => 'EUR', 'status' => 'open', 'created_by' => $actor->id,
                ]);
            }

            // All titles are fictional fixtures; the one real ISBN is labelled as a scanner test identifier.
            $products = [
                ['001', 'ISBN scanner sample (not catalogue metadata)', 'Children\'s fiction', 'book', '9780140328721', '3.2500', '7.99'],
                ['002', 'The Little Night Owl', 'Picture books', 'book', null, '2.1000', '5.99'],
                ['003', 'A Fox in the Garden', 'Picture books', 'book', null, '2.5000', '6.99'],
                ['004', 'The Secret Lighthouse', 'Children\'s fiction', 'book', null, '3.5000', '8.99'],
                ['005', 'Adventures on the Island', 'Children\'s fiction', 'book', null, '3.7500', '9.49'],
                ['006', 'The Bookshop at Dawn', 'Fiction', 'book', null, '4.2000', '10.99'],
                ['007', 'Letters from the Harbour', 'Fiction', 'book', null, '4.0000', '9.99'],
                ['008', 'A Small Guide to Stargazing', 'Non-fiction', 'book', null, '5.2000', '12.99'],
                ['009', 'Nature Walks for Families', 'Non-fiction', 'book', null, '4.5000', '11.99'],
                ['010', 'Stories for Rainy Days', 'Children\'s fiction', 'book', null, '3.1000', '7.49'],
                ['011', 'My First Counting Book', 'Picture books', 'book', null, '1.8000', '4.99'],
                ['012', 'The Curious Mapmaker', 'Fiction', 'book', null, '4.8000', '11.49'],
                ['013', 'Woodland jigsaw puzzle', 'Toys', 'other', null, '4.5000', '10.99'],
                ['014', 'Owl soft toy', 'Toys', 'other', null, '3.7500', '9.99'],
                ['015', 'Reading journal', 'Stationery', 'other', null, '2.2500', '6.49'],
            ];
            foreach ($products as $index => [$number, $title, $category, $type, $isbn, $cost, $price]) {
                // Do not take over an ISBN already registered by the user.
                $demoIsbn = $isbn && Product::where('isbn', $isbn)->where('sku', '!=', 'DEMO-'.$number)->exists() ? null : $isbn;
                $product = Product::firstOrCreate(['sku' => 'DEMO-'.$number], [
                    'name' => 'Demo: '.$title, 'type' => $type, 'isbn' => $demoIsbn, 'category_id' => $categories[$category]->id,
                ]);
                $tax = $type === 'book' ? $zero : $exampleRate;
                $quantity = 6 + ($index % 7);
                $this->receive($actor, $sessions[$type === 'book' ? 0 : 1], $product, $number.'-new', [
                    'quantity' => $quantity, 'condition' => 'new', 'cost_input' => $cost, 'selling_price' => $price,
                    'purchase_tax_category_id' => $tax->id, 'sales_tax_category_id' => $tax->id,
                    'allocations' => [
                        ['sales_channel_id' => $web->id, 'quantity' => 2],
                        ['sales_channel_id' => $shopChannel->id, 'quantity' => 2],
                        ['sales_channel_id' => $fair->id, 'quantity' => $quantity - 5],
                        ['sales_channel_id' => $unallocated->id, 'quantity' => 1],
                    ],
                ]);
                if ($number === '001') {
                    foreach (['used' => '3.99', 'almost_new' => '5.49'] as $condition => $conditionPrice) {
                        $this->receive($actor, $sessions[0], $product, $number.'-'.$condition, [
                            'quantity' => 2, 'condition' => $condition, 'cost_input' => '1.0000', 'selling_price' => $conditionPrice,
                            'purchase_tax_category_id' => $zero->id, 'sales_tax_category_id' => $zero->id,
                            'allocations' => [['sales_channel_id' => $shopChannel->id, 'quantity' => 2]],
                        ]);
                    }
                }
            }
        });

        $this->command?->info('Demo data ready: 15 products, 3 sessions, 17 receipt batches and 136 units on a fresh run. Repeat runs do not replenish stock.');
    }

    private function receive(User $actor, IntakeSession $session, Product $product, string $key, array $values): void
    {
        $uuid = (string) Uuid::uuid5(Uuid::NAMESPACE_DNS, 'wise-owl.demo.v1.'.$key);
        if (ReceiptLine::where('idempotency_key', $uuid)->exists()) {
            return;
        }
        app(ReceiveStock::class)->execute($actor, $values + [
            'idempotency_key' => $uuid, 'intake_session_id' => $session->id, 'product_id' => $product->id,
            'cost_basis' => 'exclusive', 'cost_mode' => 'per_copy', 'price_confirmed' => true, 'additional_quantity_confirmed' => true,
        ]);
    }
}
