<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Google Play kaynağı GCS nesne adını içeriyor; uzun paket adlarında 60 karakteri aşıyor.
     */
    public function up(): void
    {
        Schema::table('raw_reports', function (Blueprint $table) {
            $table->string('source', 255)->change();
        });
    }

    public function down(): void
    {
        Schema::table('raw_reports', function (Blueprint $table) {
            $table->string('source', 60)->change();
        });
    }
};
