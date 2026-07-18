<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ratings', function (Blueprint $table): void {
            $table->decimal('rating', 3, 1)->change();
            $table->timestamp('withdrawn_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('ratings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('rating')->change();
            $table->dropColumn('withdrawn_at');
        });
    }
};
