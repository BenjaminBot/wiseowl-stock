<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('catalogue_version')->default(0);
        });
        Schema::create('book_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->restrictOnDelete();
            $table->json('author_names')->nullable();
            $table->string('publisher')->nullable();
            $table->string('published_date', 64)->nullable();
            $table->string('format')->nullable();
            $table->string('language', 64)->nullable();
            $table->string('metadata_source')->nullable();
            $table->text('source_url')->nullable();
            $table->boolean('needs_review')->default(true);
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->restrictOnDelete();
            $table->string('source');
            $table->text('source_url')->nullable();
            $table->text('external_url')->nullable();
            $table->string('storage_path')->nullable();
            $table->foreignId('selected_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('book_details');
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('catalogue_version'));
    }
};
