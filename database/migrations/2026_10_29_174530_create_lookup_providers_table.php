<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lookup_providers', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('metadata_mode')->default('manual');
            $table->string('image_mode')->default('manual');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        foreach ([['open_library', 'Open Library', 'automatic', 'automatic'], ['google_books', 'Google Books', 'manual', 'manual'], ['brave', 'Brave Image Search', 'disabled', 'manual']] as [$key, $name, $metadata, $images]) {
            DB::table('lookup_providers')->insert(['key' => $key, 'name' => $name, 'metadata_mode' => $metadata,
                'image_mode' => $images, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lookup_providers');
    }
};
