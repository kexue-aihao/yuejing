<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table): void {
            $table->foreignId('category_id')->nullable()->after('novel_id')->constrained()->nullOnDelete();
            $table->index(['status', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table): void {
            $table->dropForeign(['category_id']);
            $table->dropIndex(['status', 'category_id']);
            $table->dropColumn('category_id');
        });
    }
};
