<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['templates', 'projects'] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->json('image_filters')->nullable());
        }
    }

    public function down(): void
    {
        foreach (['templates', 'projects'] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->dropColumn('image_filters'));
        }
    }
};
