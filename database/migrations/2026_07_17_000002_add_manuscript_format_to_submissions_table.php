<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table): void {
            $table->string('manuscript_format')->default('markdown')->after('manuscript');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table): void {
            $table->dropColumn('manuscript_format');
        });
    }
};
