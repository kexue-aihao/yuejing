<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chapters', function (Blueprint $table): void {
            $table->string('content_format')->default('markdown')->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('chapters', function (Blueprint $table): void {
            $table->dropColumn('content_format');
        });
    }
};
