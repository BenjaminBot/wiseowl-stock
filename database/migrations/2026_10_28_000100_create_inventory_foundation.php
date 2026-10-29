<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_staff')->default(false);
            $table->boolean('is_manager')->default(false);
        });
        foreach (['categories', 'suppliers', 'locations'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->timestamps();
            });
        }
        Schema::create('sales_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('type')->default('shop');
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });
        Schema::create('tax_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('rate', 7, 4);
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->string('type')->default('book');
            $table->string('isbn', 13)->nullable()->unique();
            $table->string('barcode')->nullable()->unique();
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('intake_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('reference');
            $table->foreignId('supplier_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('receiving_location_id')->constrained('locations')->restrictOnDelete();
            $table->json('destinations');
            $table->string('default_condition')->default('new');
            $table->boolean('website_separated')->default(true);
            $table->string('currency', 3)->default('EUR');
            $table->string('status')->default('open');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('idempotency_key')->unique();
            $table->string('request_hash', 64);
            $table->foreignId('intake_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('condition');
            $table->decimal('cost_input', 18, 4);
            $table->string('cost_basis');
            $table->string('cost_mode');
            $table->decimal('unit_net_cost', 18, 4);
            $table->decimal('purchase_tax_rate', 7, 4);
            $table->decimal('selling_price', 14, 2);
            $table->decimal('sales_tax_rate', 7, 4);
            $table->string('currency', 3);
            $table->timestamps();
        });
        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_line_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('condition');
            $table->decimal('unit_net_cost', 18, 4);
            $table->decimal('selling_price', 14, 2);
            $table->decimal('sales_tax_rate', 7, 4);
            $table->string('currency', 3);
            $table->timestamps();
        });
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('sales_channel_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('reserved_quantity')->default(0);
            $table->timestamps();
            $table->unique(['stock_batch_id', 'location_id', 'sales_channel_id'], 'stock_bucket_unique');
        });
        Schema::create('stock_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('sales_channel_id')->constrained()->restrictOnDelete();
            $table->integer('quantity_delta');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['stock_ledger_entries', 'stock_balances', 'stock_batches', 'receipt_lines', 'intake_sessions', 'products', 'tax_categories', 'sales_channels', 'locations', 'suppliers', 'categories'] as $name) {
            Schema::dropIfExists($name);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['is_staff', 'is_manager']));
    }
};
