<?php

use App\Support\BookFormat;
use App\Support\BookLanguage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('book_details', function (Blueprint $table) {
            $table->string('language_original', 64)->nullable();
            $table->string('format_original')->nullable();
            $table->index('language');
            $table->index('format');
        });
        DB::table('book_details')->orderBy('id')->chunkById(100, function ($details) {
            foreach ($details as $detail) {
                $language = BookLanguage::normalise($detail->language);
                $format = BookFormat::normalise($detail->format);
                DB::table('book_details')->where('id', $detail->id)->update([
                    'language' => $language, 'format' => $format,
                    'language_original' => $detail->language, 'format_original' => $detail->format,
                    'needs_review' => $detail->needs_review || (filled($detail->language) && $language === null) || (filled($detail->format) && $format === null),
                ]);
                DB::table('products')->where('id', $detail->product_id)->increment('catalogue_version');
            }
        });
    }

    public function down(): void
    {
        Schema::table('book_details', function (Blueprint $table) {
            $table->dropIndex(['language']);
            $table->dropIndex(['format']);
            $table->dropColumn(['language_original', 'format_original']);
        });
    }
};
